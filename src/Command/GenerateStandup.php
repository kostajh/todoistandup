<?php

namespace Kostajh\TodoistStandup\Command;

use DateTime;
use FabianBeiner\Todoist\TodoistClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateStandup extends Command
{
    private array $projectsToSkip;
    private array $tasksToSkip;
    private array $tagsToSkip;

    public function getName(): ?string
    {
        return 'generate-standup';
    }

    public function configure()
    {
        $this->addOption(
            'since',
            null,
            InputOption::VALUE_REQUIRED,
            '',
            'yesterday'
        );
        $this->addOption(
            'until',
            null,
            InputOption::VALUE_REQUIRED,
            '',
            'today'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $since = new DateTime($input->getOption('since'));
        $since->setTime(0, 0);
        $until = new DateTime($input->getOption('until'));
        $todoist = new TodoistClient($_ENV['API_KEY']);

        $this->projectsToSkip = explode(';', $_ENV['PROJECTS_TO_SKIP']);
        $this->tasksToSkip = explode(';', $_ENV['TASKS_TO_SKIP']);
        $this->tagsToSkip = explode(';', $_ENV['TAGS_TO_SKIP']);
        $projectData = $todoist->getAllProjects();
        $projects = [];
        foreach ($projectData as $projectDatum) {
            $projects[$projectDatum['id']] = $projectDatum['name'];
        }
        $output->writeln('**Yesterday**' . PHP_EOL);

        $query = http_build_query(
            [
            'since' => $since->format(\DateTimeInterface::ATOM),
            'until' => $until->format(\DateTimeInterface::ATOM),
            ]
        );
        $sync = $todoist->get(
            '/sync/v9/completed/get_all?' . $query
        );
        $yesterdayTasks = json_decode($sync->getBody()->getContents(), true)['items'];

        $groupedTasks = [];
        foreach ($yesterdayTasks as $task) {
            $project = $projects[$task['project_id']];
            $groupedTasks[$project][] = $task['content'];
        }
        $this->outputTasks($output, $groupedTasks);

        $tasks = $todoist->getAllTasks([ 'filter' => 'today | overdue' ]);
        $groupedTasks = [];
        foreach ($tasks as $task) {
            $project = $projects[$task['project_id']];
            $groupedTasks[$project][] = $task['content'];
        }
        $output->writeln(PHP_EOL . '**Today**' . PHP_EOL);
        $this->outputTasks($output, $groupedTasks);

        return Command::SUCCESS;
    }

    private function outputTasks(OutputInterface $output, array $groupedTasks): void
    {
        foreach ($groupedTasks as $project => $tasks) {
            if (in_array($project, $this->projectsToSkip)) {
                continue;
            }
            // TODO Handle empty projects
            $output->writeln('* ' . $project);
            foreach ($tasks as $task) {
                if (in_array($task, $this->tasksToSkip)) {
                    continue;
                }
                foreach ($this->tagsToSkip as $tag) {
                    if (str_contains($task, $tag)) {
                        continue 2;
                    }
                }

                $task = preg_replace('/@[a-z0-9]+/i', '', $task);
                $output->writeln('    * ' . $task);
            }
        }
    }

}
