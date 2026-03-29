<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Participant;
use App\Entity\Round;
use App\Entity\Tournament;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/v1')]
final class TournamentController extends AbstractController
{
    use ApiJsonTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/tournaments', name: 'api_v1_tournaments_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var list<Tournament> $rows */
        $rows = $this->em->getRepository(Tournament::class)->findBy([], ['startsAt' => 'ASC', 'name' => 'ASC']);

        return $this->jsonData(array_map(fn (Tournament $t) => $this->tournamentSummary($t), $rows));
    }

    #[Route('/tournaments', name: 'api_v1_tournaments_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $body = $this->parseJsonBody($request);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonDetail($e->getMessage(), 'invalid_json', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name = isset($body['name']) && \is_string($body['name']) ? trim($body['name']) : '';
        $sportKey = isset($body['sport_key']) && \is_string($body['sport_key']) ? trim($body['sport_key']) : '';
        if ($name === '' || $sportKey === '') {
            return $this->jsonDetail('name and sport_key are required', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tournament = new Tournament();
        $tournament->setName($name);
        $tournament->setSportKey($sportKey);
        if (isset($body['slug']) && \is_string($body['slug']) && $body['slug'] !== '') {
            $tournament->setSlug($body['slug']);
        }
        if (isset($body['stats_adapter_key']) && \is_string($body['stats_adapter_key'])) {
            $tournament->setStatsAdapterKey($body['stats_adapter_key']);
        }
        if (isset($body['default_scoring_engine_key']) && \is_string($body['default_scoring_engine_key'])) {
            $tournament->setDefaultScoringEngineKey($body['default_scoring_engine_key']);
        }
        if (isset($body['timezone']) && \is_string($body['timezone'])) {
            $tournament->setTimezone($body['timezone']);
        }
        if (isset($body['starts_at']) && \is_string($body['starts_at'])) {
            $tournament->setStartsAt($this->parseDate($body['starts_at']));
        }
        if (\array_key_exists('ends_at', $body)) {
            $tournament->setEndsAt(\is_string($body['ends_at']) && $body['ends_at'] !== '' ? $this->parseDate($body['ends_at']) : null);
        }
        if (isset($body['metadata']) && \is_array($body['metadata'])) {
            /** @var array<string, mixed> $meta */
            $meta = $body['metadata'];
            $tournament->setMetadata($meta);
        }

        $this->em->persist($tournament);
        $this->em->flush();

        return $this->jsonData($this->tournamentDetail($tournament), Response::HTTP_CREATED);
    }

    #[Route('/tournaments/{tournamentId}', name: 'api_v1_tournaments_get', methods: ['GET'], requirements: ['tournamentId' => Requirement::UUID])]
    public function getOne(string $tournamentId): JsonResponse
    {
        $tournament = $this->em->find(Tournament::class, $tournamentId);
        if (!$tournament instanceof Tournament) {
            return $this->jsonDetail('Tournament not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        return $this->jsonData($this->tournamentDetail($tournament));
    }

    #[Route('/tournaments/{tournamentId}/rounds', name: 'api_v1_tournaments_rounds', methods: ['GET'], requirements: ['tournamentId' => Requirement::UUID])]
    public function rounds(string $tournamentId): JsonResponse
    {
        $tournament = $this->em->find(Tournament::class, $tournamentId);
        if (!$tournament instanceof Tournament) {
            return $this->jsonDetail('Tournament not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $rounds = $tournament->getRounds()->toArray();
        usort($rounds, static fn (Round $a, Round $b) => $a->getOrderIndex() <=> $b->getOrderIndex());

        return $this->jsonData(array_map(fn (Round $r) => $this->roundPayload($r), $rounds));
    }

    #[Route('/tournaments/{tournamentId}/participants', name: 'api_v1_tournaments_participants', methods: ['GET'], requirements: ['tournamentId' => Requirement::UUID])]
    public function participants(string $tournamentId): JsonResponse
    {
        $tournament = $this->em->find(Tournament::class, $tournamentId);
        if (!$tournament instanceof Tournament) {
            return $this->jsonDetail('Tournament not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $list = $tournament->getParticipants()->toArray();
        usort($list, static fn (Participant $a, Participant $b) => strcmp($a->getDisplayName(), $b->getDisplayName()));

        return $this->jsonData(array_map(fn (Participant $p) => $this->participantPayload($p), $list));
    }

    #[Route('/tournaments/{tournamentId}/matches', name: 'api_v1_tournaments_matches', methods: ['GET'], requirements: ['tournamentId' => Requirement::UUID])]
    public function matches(string $tournamentId): JsonResponse
    {
        $tournament = $this->em->find(Tournament::class, $tournamentId);
        if (!$tournament instanceof Tournament) {
            return $this->jsonDetail('Tournament not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        return $this->jsonDetail(
            'Match entities are not mapped yet; use schema table matches after adding a Doctrine entity.',
            'not_implemented',
            Response::HTTP_NOT_IMPLEMENTED,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function tournamentSummary(Tournament $t): array
    {
        return [
            'id' => (string) $t->getId(),
            'name' => $t->getName(),
            'slug' => $t->getSlug(),
            'sport_key' => $t->getSportKey(),
            'status' => $t->getStatus(),
            'timezone' => $t->getTimezone(),
            'starts_at' => $this->formatInstant($t->getStartsAt()),
            'ends_at' => $this->formatInstant($t->getEndsAt()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tournamentDetail(Tournament $t): array
    {
        return [
            'id' => (string) $t->getId(),
            'name' => $t->getName(),
            'slug' => $t->getSlug(),
            'sport_key' => $t->getSportKey(),
            'stats_adapter_key' => $t->getStatsAdapterKey(),
            'default_scoring_engine_key' => $t->getDefaultScoringEngineKey(),
            'status' => $t->getStatus(),
            'timezone' => $t->getTimezone(),
            'starts_at' => $this->formatInstant($t->getStartsAt()),
            'ends_at' => $this->formatInstant($t->getEndsAt()),
            'metadata' => $t->getMetadata(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function roundPayload(Round $r): array
    {
        return [
            'id' => (string) $r->getId(),
            'tournament_id' => (string) $r->getTournament()?->getId(),
            'order_index' => $r->getOrderIndex(),
            'name' => $r->getName(),
            'canonical_key' => $r->getCanonicalKey(),
            'metadata' => $r->getMetadata(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function participantPayload(Participant $p): array
    {
        return [
            'id' => (string) $p->getId(),
            'tournament_id' => (string) $p->getTournament()?->getId(),
            'external_ref' => $p->getExternalRef(),
            'display_name' => $p->getDisplayName(),
            'kind' => $p->getKind(),
            'metadata' => $p->getMetadata(),
        ];
    }

    private function formatInstant(?\DateTimeImmutable $dt): ?string
    {
        if ($dt === null) {
            return null;
        }

        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    private function parseDate(string $iso): ?\DateTimeImmutable
    {
        try {
            $d = new \DateTimeImmutable($iso);

            return $d;
        } catch (\Exception) {
            return null;
        }
    }
}
