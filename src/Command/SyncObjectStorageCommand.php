<?php

namespace App\Command;

use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FilesystemReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync:object-storage',
    description: 'Add a short description for your command',
)]
class SyncObjectStorageCommand extends Command
{
    public function __construct(
        private readonly FilesystemOperator $defaultStorage,
        private readonly FilesystemOperator $scalewayStorage,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'storage',
                InputArgument::OPTIONAL,
                'Argument description',
                'default',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $storageArg = $input->getArgument('storage');

        $storage = match ($storageArg) {
            'production' => $this->scalewayStorage,
            default => $this->defaultStorage,
        };

        try {
            $images = $storage->listContents('/', FilesystemReader::LIST_DEEP);
        } catch (FilesystemException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        foreach ($images as $image) {
            $path = $image->path();
            if ($image instanceof FileAttributes) {
                $location = sprintf('%s/%s', $storageArg, $path);
                try {
                    if ($this->defaultStorage->fileExists($location)) {
                        $io->note(sprintf('Image "%s" already exists.', $path));

                        continue;
                    }
                } catch (FilesystemException $e) {
                    $io->error($e->getMessage());

                    continue;
                }
                try {

                    $contents = $storage->readStream($path);

                    $this->defaultStorage->writeStream(
                        location: $location,
                        contents: $contents,
                    );

                    $io->success(sprintf('Image "%s" successfully saved.', $path));
                } catch (FilesystemException $e) {
                    $io->error($e->getMessage());
                }
            }
        }

        return Command::SUCCESS;
    }
}
