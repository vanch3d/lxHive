<?php

/*
 * This file is part of lxHive LRS - http://lxhive.org/
 *
 * Copyright (C) 2017 Brightcookie Pty Ltd
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with lxHive. If not, see <http://www.gnu.org/licenses/>.
 *
 * For authorship information, please view the AUTHORS
 * file that was distributed with this source code.
 */

namespace API\Console;

use API\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;

use API\Admin;
use API\Admin\User as UserAdministration;

class UserCreateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('user:create')
            ->setDescription('Creates a new user')
            ->setDefinition(
                new InputDefinition(array(
                    new InputOption('email', 'e', InputOption::VALUE_OPTIONAL),
                    new InputOption('password', 'p', InputOption::VALUE_OPTIONAL),
                    new InputOption('permissions', 'pm', InputOption::VALUE_OPTIONAL),
                ))
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $userAdmin = new UserAdministration($this->getContainer());
        $helper = $this->getHelper('question');
        $admin = new Admin\Setup();

        // 1. Email
        if (null === $input->getOption('email')) {
            $question = new Question('Please enter an e-mail: ', 'untitled');
            $question->setMaxAttempts(null);
            $question->setValidator(function ($answer) {
                if (!filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Invalid email address!');
                }

                return $answer;
            });
            $email = $helper->ask($input, $output, $question);
        } else {
            $email = $input->getOption('email');
        }

        // 2. Password
        if (null === $input->getOption('password')) {
            $question = new Question('Please enter a password: ', '');
            $question->setMaxAttempts(null);
            $question->setValidator(function ($answer) use ($admin) {
                $admin->validatePassword($answer);
                return $answer;
            });
            $password = $helper->ask($input, $output, $question);
        } else {
            $password = $input->getOption('password');
        }

        // 3. Permissions
        $permissionsDictionary = $userAdmin->fetchAvailablePermissions();
        $permissions = array_keys($permissionsDictionary);

        if (null === $input->getOption('permissions')) {
            $question = new ChoiceQuestion(
                'Please select which permissions you would like to enable (defaults to super). Separate multiple values with commas (without spaces). If you select super, all other permissions are also inherited: ',
                $permissions,
                '0'
            );
            $question->setMultiselect(true);
            $question->setMaxAttempts(null);
            $question->setValidator(function ($answer) use ($admin, $permissions) {
                $admin->validatePermissionsInput($answer, $permissions);
                return $answer;
            });

            $selectedIndexes = $helper->ask($input, $output, $question);
        }

        $selected = [];
        foreach ($selectedIndexes as $index) {
            $perm = $permissions[$index];
            $selected[] = $permissionsDictionary[$perm];
        }

        $user = $userAdmin->addUser($email, $password, $selected);
        $text = json_encode($user, JSON_PRETTY_PRINT);

        $output->writeln('<info>User successfully created!</info>');
        $output->writeln('<info>Info:</info>');
        $output->writeln($text);
    }
}
