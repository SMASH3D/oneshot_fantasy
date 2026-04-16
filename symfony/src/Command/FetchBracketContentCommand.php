<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Tournament;
use App\Service\BracketContentFetcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fetches the ESPN bracket page and stores generated CMS content on a tournament.
 *
 * Usage:
 *   php bin/console app:tournament:fetch-bracket nba-playoffs-2025
 *   php bin/console app:tournament:fetch-bracket --all
 */
#[AsCommand(
    name: 'app:tournament:fetch-bracket',
    description: 'Fetch ESPN bracket page and store CMS content on a tournament.',
)]
final class FetchBracketContentCommand extends Command
{
    public function __construct(
        private readonly BracketContentFetcher $fetcher,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('slug', InputArgument::OPTIONAL, 'Tournament slug (e.g. nba-playoffs-2025)')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Process all nba_postseason tournaments');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $repo         = $this->entityManager->getRepository(Tournament::class);
        $tournaments  = [];

        if ($input->getOption('all')) {
            $tournaments = $repo->findBy(['bracketType' => 'nba_postseason']);
            if (empty($tournaments)) {
                $io->warning('No tournaments with bracketType = nba_postseason found.');
                return Command::SUCCESS;
            }
        } else {
            $slug = $input->getArgument('slug');
            if ($slug) {
                $t = $repo->findOneBy(['slug' => $slug]);
                if (!$t) {
                    $io->error("Tournament not found: {$slug}");

                    return Command::FAILURE;
                }
                $tournaments = [$t];
            } else {
                $io->error('Provide a slug argument or use --all.');

                return Command::FAILURE;
            }
        }

        foreach ($tournaments as $tournament) {
            $io->section($tournament->getName());
            $result = $this->fetcher->fetchAndStore($tournament);
            $io->success($result);
        }

        return Command::SUCCESS;
    }
}
