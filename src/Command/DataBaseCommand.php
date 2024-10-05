<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:database:dump',
    description: 'Dump the database to a file in the .database/ directory',
)]
final class DataBaseCommand extends Command
{
    private string $databaseUrl;

    public function __construct()
    {
        parent::__construct();
        $this->databaseUrl = $_ENV['DATABASE_URL'] ?? null;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Parse de la DATABASE_URL pour en extraire les informations
        $dbParams = parse_url($this->databaseUrl);

        // Vérification de l'URL pour éviter les erreurs de connexion
        if (!$dbParams || !isset($dbParams['scheme'], $dbParams['host'], $dbParams['path'])) {
            $output->writeln('<error>La DATABASE_URL est mal formatée.</error>');
            return Command::FAILURE;
        }

        // Extraction des paramètres
        $dbHost = $dbParams['host'];
        $dbName = ltrim($dbParams['path'], '/');  // Le nom de la base est dans le path
        $dbUser = $dbParams['user'] ?? 'root';    // Utilisateur
        $dbPassword = $dbParams['pass'] ?? '';    // Mot de passe
        $dbPort = $dbParams['port'] ?? 3306;      // Port

        $dumpFilePath = sprintf('%s/.database/dump_%s.sql', getcwd(), date('Y_m_d_H_i_s'));

        // Créer le répertoire s'il n'existe pas
        $filesystem = new Filesystem();
        $filesystem->mkdir(getcwd() . '/.database');

        // Commande mysqldump
        $process = new Process([
            'mysqldump',
            '--user=' . $dbUser,
            '--password=' . $dbPassword,
            '--host=' . $dbHost,
            '--port=' . $dbPort,
            $dbName,
            '--result-file=' . $dumpFilePath
        ]);

        // Lancer le process et attendre qu'il se termine
        $process->run();

        // Vérification si la commande a réussi
        if (!$process->isSuccessful()) {
            $output->writeln('<error>Le dump de la base de données a échoué :</error>');
            $output->writeln($process->getErrorOutput());
            return Command::FAILURE;
        }

        $output->writeln('<info>Base de données sauvegardée avec succès dans ' . $dumpFilePath . '</info>');
        return Command::SUCCESS;
    }
}
