<?php

declare(strict_types=1);

namespace App\Entrypoint\Console;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ТЗ: аутентификация. Создаёт пользователя для входа на login-странице.
 * Если пользователь уже есть, команда ничего не меняет (её безопасно вызывать при каждом деплое).
 */
#[AsCommand(name: 'app:user:create', description: 'Создать пользователя для входа в приложение')]
final class CreateUserCommand extends Command
{
    /** Работает через доменный порт хранилища пользователей. */
    public function __construct(private readonly UserRepository $users)
    {
        parent::__construct();
    }

    /** Аргументы: логин и пароль (в docker compose берутся из ADMIN_USERNAME и ADMIN_PASSWORD). */
    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED);
    }

    /** Создаёт пользователя с хешем пароля (password_hash), повторный вызов не дублирует запись. */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = (string) $input->getArgument('username');
        if (null !== $this->users->findByUsername($username)) {
            $output->writeln(\sprintf('User "%s" already exists', $username));

            return self::SUCCESS;
        }

        $this->users->save(User::register($username, (string) $input->getArgument('password')));
        $output->writeln(\sprintf('User "%s" created', $username));

        return self::SUCCESS;
    }
}
