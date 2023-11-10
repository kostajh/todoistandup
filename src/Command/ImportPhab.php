<?php

namespace Kostajh\TodoistStandup\Command;

use FabianBeiner\Todoist\TodoistClient;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ImportPhab extends Command
{
    public function getName(): ?string
    {
        return 'import:phab';
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $tasks = [];
        $process = new Process([ 'arc', 'tasks' ], '/Users/kostajh');
        $process->run();
        $arcOutput = $process->getOutput();
        $rawTasks = array_filter(explode(PHP_EOL, $arcOutput));
        foreach ($rawTasks as $taskItem) {
            $taskItem = trim(str_replace([ ' Needs Triage ', ' High ', ' Medium ', ' Low ', ' In Progress', ' Open' ], '', $taskItem));
            [ $taskId ] = explode(" ", $taskItem);
            $tasks[$taskId] = trim(str_replace($taskId, "", $taskItem));
        }

        $todoist = new TodoistClient($_ENV['API_KEY']);
        $todoistTasks = $todoist->getAllTasks([ 'label' => 'phabricator' ]);
        $indexedTodoistTasks = [];
        foreach ($todoistTasks as $task) {
            $indexedTodoistTasks[trim($task['description'])] = $task['content'];
        }

        // Add new tasks to inbox.
        foreach ($tasks as $id => $task) {
            if (!isset($indexedTodoistTasks[$id])) {
                $output->writeln('<info>Adding ' . $task. ' (' . $id. ') to #inbox</info>');
                $formattedTask = "$id: [$task](https://phabricator.wikimedia.org/$id)";
                $todoist->createTask($formattedTask, [
                    'description' => $id,
                    'labels' => [
                        'phabricator',
                    ]
                ]);
            } else {
                $output->writeln('<comment>' . 'Skipping ' . $id. ' as it already is tracked.</comment>');
            }
        }

        return Command::SUCCESS;
    }
}
