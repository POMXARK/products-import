<?php

declare(strict_types=1);

namespace App\Entrypoint\Console;

use App\Infrastructure\Persistence\Doctrine\EntityManagerProvider;
use App\Infrastructure\Persistence\Fixtures\ProductFixtures;
use App\Infrastructure\Persistence\Fixtures\UserFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ТЗ: "сидеры (fixtures) для всех трёх таблиц".
 * Очищает таблицы и загружает демо-товары с атрибутами и изображениями плюс пользователя для входа.
 */
#[AsCommand(name: 'app:fixtures:load', description: 'Очистить БД и загрузить фикстуры')]
final class LoadFixturesCommand extends Command
{
    /**
     * Окружение и учётка администратора берутся из настроек приложения.
     *
     * @param array{admin: array{username: string, password: string}, env: string} $settings
     */
    public function __construct(
        private readonly EntityManagerProvider $provider,
        private readonly array $settings,
    ) {
        parent::__construct();
    }

    /** Имя app:fixtures:load и флаг --force для запуска в prod. */
    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Разрешить запуск в prod-окружении');
    }

    /** Защищено от случайного запуска в prod: очистка БД там без --force запрещена. */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ('prod' === $this->settings['env'] && !$input->getOption('force')) {
            $output->writeln('<error>APP_ENV=prod: fixtures wipe the database, pass --force to continue</error>');

            return self::FAILURE;
        }

        $loader = new Loader();
        $loader->addFixture(new UserFixtures($this->settings['admin']['username'], $this->settings['admin']['password']));
        $loader->addFixture(new ProductFixtures());

        $em = $this->provider->get();
        $purger = new ORMPurger($em);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);
        (new ORMExecutor($em, $purger))->execute($loader->getFixtures());

        $output->writeln(\sprintf('Loaded: 1 user, %d products with attributes and images', ProductFixtures::COUNT));

        return self::SUCCESS;
    }
}
