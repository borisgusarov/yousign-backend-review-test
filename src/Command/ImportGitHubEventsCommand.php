<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Event;
use App\Entity\Actor;
use App\Entity\Repo;
use \App\Entity\EventType;
use App\Repository\DbalWriteEventRepository;
use App\Repository\DbalWriteActorRepository;
use App\Repository\DbalWriteRepoRepository;

/**
 * This command must import GitHub events.
 * You can add the parameters and code you want in this command to meet the need.
 */
class ImportGitHubEventsCommand extends Command
{
    protected static $defaultName = 'app:import-github-events';

    private EntityManagerInterface $em;
    private DbalWriteEventRepository $writeEventRepository;
    private DbalWriteActorRepository $writeActorRepository;
    private DbalWriteRepoRepository $writeRepoRepository;

    // Allowed event types to import
    // We only support a subset of GitHub event types, the rest will be skipped.
    private const SUPPORTED_EVENT_TYPES = [
        'PushEvent',
        'PullRequestEvent',
        'CommitCommentEvent',
    ];

    public function __construct(
        EntityManagerInterface $em,
        DbalWriteEventRepository $writeEventRepository,
        DbalWriteActorRepository $writeActorRepository,
        DbalWriteRepoRepository $writeRepoRepository
    ) {
        parent::__construct();
        $this->em = $em;
        $this->writeEventRepository = $writeEventRepository;
        $this->writeActorRepository = $writeActorRepository;
        $this->writeRepoRepository = $writeRepoRepository;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Import GH events')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date to import (YYYY-MM-DD-H)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $date = $input->getOption('date');
        if (!$date) {
            $output->writeln('<error>The --date option is required.</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>Importing GitHub events for date: $date</info>");

        $imported = 0;
        $processed = 0;
        $failed = 0;

        $url = sprintf('https://data.gharchive.org/%s.json.gz', $date);
        $output->writeln("Downloading $url ...");

        // Download the archive and split into smaller files (e.g., 10,000 lines per chunk)
        $tmpDir = sys_get_temp_dir() . '/gharchive_' . uniqid();
        if (!mkdir($tmpDir) && !is_dir($tmpDir)) {
            $output->writeln("<error>Could not create temp directory $tmpDir</error>");
            return Command::FAILURE;
        }

        $fileContents = @file_get_contents($url);
        if ($fileContents === false) {
            $error = error_get_last(); // Use Try Catch here
            $errorMsg = $error ? $error['message'] : 'Unknown error';
            $output->writeln("<comment>Could not download $url, skipping. Error: $errorMsg</comment>");
            return Command::FAILURE;
        }

        $archiveFile = $tmpDir . '/archive.json.gz';
        file_put_contents($archiveFile, $fileContents);

        // Split the archive into smaller gzipped files
        $chunkFiles = [];
        $linesPerChunk = 1000;
        $gz = @gzopen($archiveFile, 'r');
        if (!$gz) {
            $output->writeln("<error>Could not open $archiveFile</error>");
            return Command::FAILURE;
        }
        $chunkIndex = 0;
        $lines = 0;
        $chunkFile = $tmpDir . "/chunk_$chunkIndex.json.gz";
        $chunkGz = gzopen($chunkFile, 'w');
        $chunkFiles[] = $chunkFile;
        while (!gzeof($gz)) {
            $line = gzgets($gz);
            if ($line === false) break;
            gzwrite($chunkGz, $line);
            $lines++;
            if ($lines % $linesPerChunk === 0) {
                gzclose($chunkGz);
                $chunkIndex++;
                $chunkFile = $tmpDir . "/chunk_$chunkIndex.json.gz";
                $chunkGz = gzopen($chunkFile, 'w');
                $chunkFiles[] = $chunkFile;
            }
        }
        gzclose($chunkGz);
        gzclose($gz);

        // To avoid memory issues, process each chunk file one by one
        foreach ($chunkFiles as $chunkFile) {
            $tmpFile = $chunkFile;
            $output->writeln("Processing chunk file: $tmpFile");
            $processed += $this->processChunkFile($tmpFile, $output, $processed, $imported, $failed);
            @unlink($tmpFile);
            $output->writeln("Current memory usage: " . number_format(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n");
        }

        $output->writeln("<info>Done. Processed: $processed, Imported: $imported, Failed: $failed</info>");
        return Command::SUCCESS;
    }

    /**
     * Processes a chunk file containing GitHub events.
     *
     * This method handles the logic for reading and processing a chunk file,
     * used during the import of GitHub events.
     *
     * @param string $filePath The path to the chunk file to process.
     * @return void
     */
    private function processChunkFile(
        string $tmpFile,
        OutputInterface $output,
        int $processed,
        int $imported,
        int $failed
    ): int {
        $output->writeln("Processing chunk file ...");

        /** @var array<int, Actor> $uniqueActors */
        $uniqueActors = [];
        /** @var array<int, Repo> $uniqueRepos */
        $uniqueRepos = [];
        /** @var array<int, array> $uniqueEvents */
        $uniqueEvents = [];

        // Open and loop over the gzipped file line by line
        $stream = @gzopen($tmpFile, 'r');
        if ($stream) {
            while (!gzeof($stream)) {
                $line = gzgets($stream);
                if (!$line) continue;
                $event = json_decode($line, true);
                if (!$event) continue;
                if (!in_array($event['type'], self::SUPPORTED_EVENT_TYPES)) {
                    continue; // Skip unsupported event types
                }

                // Build Unique actors
                $actorId = (int)$event['actor']['id'];
                if (!isset($uniqueActors[$actorId])) {
                    $actorData = $event['actor'];
                    $uniqueActors[$actorId] = new Actor(
                        id: $actorId,
                        login: $actorData['login'] ?? null,
                        url: $actorData['url'] ?? null,
                        avatarUrl: $actorData['avatar_url'] ?? null
                    );
                }

                // Build Unique repos
                $repoId = (int)$event['repo']['id'];
                if (!isset($uniqueRepos[$repoId])) {
                    $uniqueRepos[$repoId] = new Repo(
                        id: $repoId,
                        name: $event['repo']['name'] ?? null,
                        url: $event['repo']['url'] ?? null
                    );
                }

                // Build unique Event entities directly, using previously built arrays of Actors and Repos
                $eventId = (int)$event['id'];
                if (!isset($uniqueEvents[$eventId])) {
                    $actorId = (int)$event['actor']['id'];
                    $repoId = (int)$event['repo']['id'];
                    if (isset($uniqueActors[$actorId]) && isset($uniqueRepos[$repoId])) {
                        // Map type to EventType enum
                        $eventTypeMap = [
                            'PushEvent' => EventType::COMMIT,
                            'PullRequestEvent' => EventType::PULL_REQUEST,
                            'CommitCommentEvent' => EventType::COMMENT,
                        ];
                        $type = $event['type'];
                        $createdAt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $event['created_at']);
                        $comment = null;
                        $uniqueEvents[$eventId] = $event;
                    }
                }
            }
            gzclose($stream);
        }

        $output->writeln("Found " . count($uniqueActors) . " unique actors.");
        $this->writeActorRepository->upsertMany(array_values($uniqueActors));
        $output->writeln("Actors inserted.");

        $output->writeln("Found " . count($uniqueRepos) . " unique repos.");
        $this->writeRepoRepository->upsertMany(array_values($uniqueRepos));
        $output->writeln("Repos inserted.");

        $output->writeln("Found " . count($uniqueEvents) . " unique events.");
        // $this->writeEventRepository->upsertMany(array_values($uniqueEvents));

        if (count($uniqueEvents) > 0) {
            $newEvents = [];
            foreach ($uniqueEvents as $event) {
                $entity = $this->mapEventToEntityWithReferences($event);
                if ($entity) {
                    $newEvents[] = $entity;
                }
            }
            $this->writeEventRepository->upsertMany($newEvents);
        }
        $output->writeln("Events inserted.");

        return $imported;
    }

    /**
     * Maps a GitHub event array to an Event entity with related references.
     *
     * @param array $event The GitHub event data to map.
     * @return Event|null The mapped Event entity, or null if mapping fails.
     */
    private function mapEventToEntityWithReferences(array $event): Event | null
    {
        // Extract and validate required fields
        if (!isset($event['id'], $event['type'], $event['actor'], $event['repo'], $event['payload'], $event['created_at'])) {
            throw new \InvalidArgumentException('Missing required event fields');
        }

        // We only support a subset of GitHub event types, the rest will be skipped.

        // Map type to EventType enum
        // Use PushEvent, PullRequestEvent and CommitCommentEvent.
        $type = $event['type'];
        $eventTypeMap = [
            'PushEvent' => EventType::COMMIT,
            'PullRequestEvent' => EventType::PULL_REQUEST,
            'CommitCommentEvent' => EventType::COMMENT,
        ];
        if (!in_array($type, self::SUPPORTED_EVENT_TYPES)) {
            return null; // Skip unsupported event types
        }
        $actorId = (int)$event['actor']['id'];
        $repoId = (int)$event['repo']['id'];
        $actor = $this->em->getReference(Actor::class, $actorId);
        $repo = $this->em->getReference(Repo::class, $repoId);
        $createdAt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $event['created_at']);

        // Comment is not present in GH event, set to null to start with
        $comment = null;

        // Build Event entity
        return new Event(
            id: (int)$event['id'],
            type: $eventTypeMap[$type],
            actor: $actor,
            repo: $repo,
            payload: $event['payload'],
            createdAt: $createdAt,
            comment: $comment
        );
    }
}
