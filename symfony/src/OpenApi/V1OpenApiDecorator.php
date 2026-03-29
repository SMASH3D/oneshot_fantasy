<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\Model\Tag;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Merges custom REST routes (/api/v1) into API Platform's OpenAPI document.
 * Without this, /api/docs stays empty when no #[ApiResource] classes exist.
 */
final class V1OpenApiDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $paths = $this->copyPaths($openApi->getPaths());
        foreach ($this->buildV1PathItems() as $path => $item) {
            $paths->addPath($path, $item);
        }

        $tags = $openApi->getTags();
        foreach ($this->v1Tags() as $tag) {
            $tags[] = $tag;
        }

        return $openApi->withPaths($paths)->withTags($tags);
    }

    private function copyPaths(Paths $source): Paths
    {
        $out = new Paths();
        foreach ($source->getPaths() as $path => $item) {
            $out->addPath($path, $item);
        }

        return $out;
    }

    /**
     * @return Tag[]
     */
    private function v1Tags(): array
    {
        return [
            new Tag('Tournaments', 'Custom JSON API — /api/v1/tournaments'),
            new Tag('Leagues', 'Custom JSON API — /api/v1/leagues'),
            new Tag('Draft', 'Custom JSON API — draft under a league'),
            new Tag('Lineups', 'Custom JSON API — lineup submissions'),
            new Tag('Scores & leaderboard', 'Custom JSON API — scores and ranks'),
        ];
    }

    /**
     * @return array<string, PathItem>
     */
    private function buildV1PathItems(): array
    {
        $uuid = ['type' => 'string', 'format' => 'uuid'];
        $err = $this->errorResponses();

        $pTournament = [new Parameter('tournamentId', 'path', 'Tournament id', true, false, null, $uuid)];
        $pLeague = [new Parameter('leagueId', 'path', 'League id', true, false, null, $uuid)];
        $pFantasyRound = [
            new Parameter('leagueId', 'path', 'League id', true, false, null, $uuid),
            new Parameter('fantasyRoundId', 'path', 'Fantasy round id', true, false, null, $uuid),
        ];
        $pLineup = [
            ...$pFantasyRound,
            new Parameter('membershipId', 'path', 'League membership id', true, false, null, $uuid),
        ];

        return [
            '/api/v1/tournaments' => (new PathItem())
                ->withGet($this->op('api_v1_tournaments_list', 'Tournaments', 'List tournaments', [
                    '200' => $this->jsonListResponse('Tournament summaries'),
                ] + $err))
                ->withPost($this->op('api_v1_tournaments_create', 'Tournaments', 'Create tournament', [
                    '201' => $this->jsonObjResponse('Created tournament'),
                ] + $err, $this->jsonBody([
                    'type' => 'object',
                    'required' => ['name', 'sport_key'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'sport_key' => ['type' => 'string'],
                        'stats_adapter_key' => ['type' => 'string'],
                        'default_scoring_engine_key' => ['type' => 'string'],
                        'timezone' => ['type' => 'string'],
                        'starts_at' => ['type' => 'string', 'format' => 'date-time'],
                        'ends_at' => ['type' => 'string', 'format' => 'date-time'],
                        'metadata' => ['type' => 'object'],
                    ],
                ]))),

            '/api/v1/tournaments/{tournamentId}' => (new PathItem())
                ->withGet($this->op('api_v1_tournaments_get', 'Tournaments', 'Get tournament', [
                    '200' => $this->jsonObjResponse('Tournament'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, $pTournament)),

            '/api/v1/tournaments/{tournamentId}/rounds' => (new PathItem())
                ->withGet($this->op('api_v1_tournaments_rounds', 'Tournaments', 'List tournament rounds (bracket)', [
                    '200' => $this->jsonListResponse('Rounds'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, $pTournament)),

            '/api/v1/tournaments/{tournamentId}/participants' => (new PathItem())
                ->withGet($this->op('api_v1_tournaments_participants', 'Tournaments', 'List participants', [
                    '200' => $this->jsonListResponse('Participants'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, $pTournament)),

            '/api/v1/tournaments/{tournamentId}/matches' => (new PathItem())
                ->withGet($this->op('api_v1_tournaments_matches', 'Tournaments', 'List matches (not implemented)', [
                    '501' => $this->detailResponse('Not implemented until Match entity exists'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, $pTournament)),

            '/api/v1/tournaments/{tournamentId}/leagues' => (new PathItem())
                ->withGet($this->op('api_v1_tournaments_leagues_list', 'Leagues', 'List leagues for tournament', [
                    '200' => $this->jsonListResponse('League summaries'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, $pTournament))
                ->withPost($this->op('api_v1_tournaments_leagues_create', 'Leagues', 'Create league', [
                    '201' => $this->jsonObjResponse('League'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, $this->jsonBody([
                    'type' => 'object',
                    'required' => ['name', 'commissioner_user_id'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'commissioner_user_id' => $uuid,
                        'settings' => ['type' => 'object'],
                        'lineup_template' => ['type' => 'array', 'items' => ['type' => 'object']],
                    ],
                ]), $pTournament)),

            '/api/v1/leagues' => (new PathItem())
                ->withGet($this->op('api_v1_leagues_list', 'Leagues', 'List leagues', [
                    '200' => $this->jsonListResponse('League summaries'),
                ] + $err, null, [
                    new Parameter('tournament_id', 'query', 'Filter by tournament UUID', false, false, null, $uuid),
                ])),

            '/api/v1/leagues/{leagueId}' => (new PathItem())
                ->withGet($this->op('api_v1_leagues_get', 'Leagues', 'Get league', [
                    '200' => $this->jsonObjResponse('League'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, $pLeague)),

            '/api/v1/leagues/{leagueId}/members' => (new PathItem())
                ->withGet($this->op('api_v1_leagues_members_list', 'Leagues', 'List members', [
                    '200' => $this->jsonListResponse('Memberships'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, $pLeague))
                ->withPost($this->op('api_v1_leagues_members_create', 'Leagues', 'Add member', [
                    '201' => $this->jsonObjResponse('Membership'),
                    '404' => $this->detailResponse('Not found'),
                    '409' => $this->detailResponse('Conflict'),
                ] + $err, $this->jsonBody([
                    'type' => 'object',
                    'required' => ['user_id'],
                    'properties' => [
                        'user_id' => $uuid,
                        'nickname' => ['type' => 'string'],
                    ],
                ]), $pLeague)),

            '/api/v1/leagues/{leagueId}/rounds' => (new PathItem())
                ->withGet($this->op('api_v1_leagues_rounds', 'Leagues', 'List fantasy rounds', [
                    '200' => $this->jsonListResponse('Fantasy rounds'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, $pLeague)),

            '/api/v1/leagues/{leagueId}/draft/configure' => (new PathItem())
                ->withPost($this->op('api_v1_draft_configure', 'Draft', 'Configure draft', [
                    '200' => $this->jsonObjResponse('Draft session'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, $this->jsonBody([
                    'type' => 'object',
                    'properties' => [
                        'snake' => ['type' => 'boolean'],
                        'pick_time_seconds' => ['type' => 'integer'],
                        'order_membership_ids' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uuid']],
                    ],
                ]), $pLeague)),

            '/api/v1/leagues/{leagueId}/draft/start' => (new PathItem())
                ->withPost($this->op('api_v1_draft_start', 'Draft', 'Start draft', [
                    '200' => $this->jsonObjResponse('Draft started'),
                    '404' => $this->detailResponse('Not found'),
                    '409' => $this->detailResponse('Conflict'),
                ] + $err, null, $pLeague)),

            '/api/v1/leagues/{leagueId}/draft' => (new PathItem())
                ->withGet($this->op('api_v1_draft_get', 'Draft', 'Get draft state', [
                    '200' => $this->jsonObjResponse('Draft state'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, $pLeague)),

            '/api/v1/leagues/{leagueId}/draft/picks' => (new PathItem())
                ->withPost($this->op('api_v1_draft_pick', 'Draft', 'Record a pick', [
                    '201' => $this->jsonObjResponse('Pick'),
                    '404' => $this->detailResponse('Not found'),
                    '409' => $this->detailResponse('Conflict'),
                ] + $err, $this->jsonBody([
                    'type' => 'object',
                    'required' => ['league_membership_id', 'participant_id'],
                    'properties' => [
                        'league_membership_id' => $uuid,
                        'participant_id' => $uuid,
                    ],
                ]), $pLeague)),

            '/api/v1/leagues/{leagueId}/draft/complete' => (new PathItem())
                ->withPost($this->op('api_v1_draft_complete', 'Draft', 'Complete draft', [
                    '200' => $this->jsonObjResponse('Draft completed'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, $pLeague)),

            '/api/v1/leagues/{leagueId}/rounds/{fantasyRoundId}/lineups/{membershipId}' => (new PathItem())
                ->withGet($this->op('api_v1_lineup_get', 'Lineups', 'Get lineup', [
                    '200' => $this->jsonObjResponse('Lineup'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, $pLineup))
                ->withPut($this->op('api_v1_lineup_put', 'Lineups', 'Update lineup slots', [
                    '200' => $this->jsonObjResponse('Lineup'),
                    '404' => $this->detailResponse('Not found'),
                    '409' => $this->detailResponse('Conflict'),
                ] + $err, $this->jsonBody([
                    'type' => 'object',
                    'required' => ['slots'],
                    'properties' => [
                        'slots' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'order_index' => ['type' => 'integer'],
                                    'slot_role' => ['type' => 'string'],
                                    'participant_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                                ],
                            ],
                        ],
                    ],
                ]), $pLineup)),

            '/api/v1/leagues/{leagueId}/rounds/{fantasyRoundId}/lineups/{membershipId}/submit' => (new PathItem())
                ->withPost($this->op('api_v1_lineup_submit', 'Lineups', 'Submit / lock lineup', [
                    '200' => $this->jsonObjResponse('Submit result'),
                    '404' => $this->detailResponse('Not found'),
                    '409' => $this->detailResponse('Conflict'),
                ] + $err, null, $pLineup)),

            '/api/v1/leagues/{leagueId}/rounds/{fantasyRoundId}/scores' => (new PathItem())
                ->withGet($this->op('api_v1_scores_round', 'Scores & leaderboard', 'Scores for one fantasy round', [
                    '200' => $this->jsonObjResponse('Round scores'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, [
                    new Parameter('leagueId', 'path', 'League id', true, false, null, $uuid),
                    new Parameter('fantasyRoundId', 'path', 'Fantasy round id', true, false, null, $uuid),
                ])),

            '/api/v1/leagues/{leagueId}/scores' => (new PathItem())
                ->withGet($this->op('api_v1_scores_cumulative', 'Scores & leaderboard', 'Cumulative scores', [
                    '200' => $this->jsonObjResponse('Cumulative scores'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, $pLeague)),

            '/api/v1/leagues/{leagueId}/leaderboard' => (new PathItem())
                ->withGet($this->op('api_v1_leaderboard', 'Scores & leaderboard', 'Leaderboard (cumulative or one round)', [
                    '200' => $this->jsonObjResponse('Leaderboard rows'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, [
                    ...$pLeague,
                    new Parameter('fantasy_round_id', 'query', 'If set, rank for this round only', false, false, null, $uuid),
                ])),

            '/api/v1/leagues/{leagueId}/rounds/{fantasyRoundId}/scores/recompute' => (new PathItem())
                ->withPost($this->op('api_v1_scores_recompute', 'Scores & leaderboard', 'Request score recompute (stub)', [
                    '202' => $this->jsonObjResponse('Accepted'),
                    '404' => $this->detailResponse('Not found'),
                ] + $err, null, [
                    new Parameter('leagueId', 'path', 'League id', true, false, null, $uuid),
                    new Parameter('fantasyRoundId', 'path', 'Fantasy round id', true, false, null, $uuid),
                ])),
        ];
    }

    /**
     * @param array<string, Response> $responses
     * @param Parameter[]|null        $parameters
     */
    private function op(
        string $operationId,
        string $tag,
        string $summary,
        array $responses,
        ?RequestBody $requestBody = null,
        ?array $parameters = null,
    ): Operation {
        return new Operation(
            $operationId,
            [$tag],
            $responses,
            $summary,
            null,
            null,
            $parameters,
            $requestBody,
        );
    }

    private function jsonBody(array $schema): RequestBody
    {
        return new RequestBody(
            'JSON body',
            new \ArrayObject([
                'application/json' => new MediaType(new \ArrayObject($schema)),
            ]),
            true,
        );
    }

    private function jsonObjResponse(string $description): Response
    {
        return new Response($description, new \ArrayObject([
            'application/json' => new MediaType(new \ArrayObject(['type' => 'object'])),
        ]));
    }

    private function jsonListResponse(string $description): Response
    {
        return new Response($description, new \ArrayObject([
            'application/json' => new MediaType(new \ArrayObject([
                'type' => 'array',
                'items' => ['type' => 'object'],
            ])),
        ]));
    }

    private function detailResponse(string $description): Response
    {
        return new Response($description, new \ArrayObject([
            'application/json' => new MediaType(new \ArrayObject([
                'type' => 'object',
                'properties' => [
                    'detail' => ['type' => 'string'],
                    'code' => ['type' => 'string'],
                ],
            ])),
        ]));
    }

    /**
     * @return array<string, Response>
     */
    private function errorResponses(): array
    {
        return [
            '422' => $this->detailResponse('Validation / bad JSON'),
        ];
    }
}
