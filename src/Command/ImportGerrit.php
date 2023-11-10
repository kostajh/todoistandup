<?php

namespace Kostajh\TodoistStandup\Command;

use FabianBeiner\Todoist\TodoistClient;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Rules:
 * - Copy gerrit code reviews into my inbox if they don't exist
 * - Mark todoist @gerrit tasks as completed if they don't exist in Gerrit feed
 */
class ImportGerrit extends Command
{
    public function getName(): ?string
    {
        return 'import:gerrit';
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $patchesToReview = [];
        $client = new Client();
        // Get all gerrit patch reviews assigned to me.
        $response = $client->request('GET', 'https://gerrit.wikimedia.org/r/changes/', [
            'query' => [
                'n' => 200,
                'q' => '-owner:kharlan@wikimedia.org status:open -is:wip (reviewer:kharlan@wikimedia.org OR assignee:kharlan@wikimedia.org)'
            ]
        ]);

        $body = $response->getBody();
        $patches = json_decode(ltrim($body, ")]}'"), true);
        foreach ($patches as $value) {
            $patchesToReview[$value['change_id']] = $value['subject'];
        }
        $todoist = new TodoistClient($_ENV['API_KEY']);
        $todoistTasks = $todoist->getAllTasks([ 'label' => 'gerrit' ]);
        $indexedTodoistTasks = [];
        foreach ($todoistTasks as $task) {
            $indexedTodoistTasks[trim($task['description'])] = $task['content'];
        }

        // Add new tasks to inbox.
        foreach ($patchesToReview as $id => $patch) {
            if (!isset($indexedTodoistTasks[$id])) {
                $output->writeln('<info>Adding ' . $patch . ' (' . $id. ') to #inbox</info>');
                $formattedTask = "CR: [$patch](https://gerrit.wikimedia.org/r/q/$id)";
                $todoist->createTask($formattedTask, [
                    'description' => $id,
                    'labels' => [
                        'codereview',
                        'gerrit'
                    ]
                ]);
            } else {
                $output->writeln('<comment>' . 'Skipping ' . $patch . ' as it already is tracked.</comment>');
            }
        }

        return Command::SUCCESS;
    }

}
