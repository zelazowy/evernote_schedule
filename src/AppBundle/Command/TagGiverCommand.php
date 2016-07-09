<?php

namespace AppBundle\Command;

use AppBundle\Entity\Auth;
use Doctrine\ORM\EntityManager;
use EDAM\NoteStore\NoteFilter;
use EDAM\NoteStore\NoteList;
use EDAM\NoteStore\NoteMetadata;
use EDAM\NoteStore\NotesMetadataResultSpec;
use EDAM\Types\Tag;
use Evernote\AdvancedClient;
use Evernote\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TagGiverCommand extends Command
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this
            ->setName("app:tag_giver")
            ->setDescription("Adds 'today' tag to all notes that have reminders for today");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $auth = $this->entityManager->getRepository(Auth::class)->find(1);

        $token = $auth->getToken();

        $advancedClient = new AdvancedClient($token, true);

        $client = new Client($token, true, $advancedClient);

        $nFilter = new NoteFilter();
        $nFilter->words = "reminderOrder:* reminderTime:day -reminderDoneTime:*";
        $nFilter->order = "created";
        $rSpec = new NotesMetadataResultSpec();

        try {
            /** @var NoteList $noteList */
            $noteList = $client->getUserNotestore()->findNotesMetadata($client->getToken(), $nFilter, 0, 50, $rSpec);
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());

            return;
        }

        $tagsRaw = $client->getUserNotestore()->listTags($client->getToken());
        $tags = [];
        /** @var Tag $tag */
        foreach ($tagsRaw as $tag) {
            $tags[$tag->name] = $tag->guid;
        }

        $notes = (array) $noteList->notes;

        /** @var NoteMetadata[] $notes */
        foreach ($notes as $n) {
            /** @var \EDAM\Types\Note $note */
            $note = $client->getUserNotestore()->getNote($client->getToken(), $n->guid, true, true, true, true);
            $note->tagGuids[] = $tags["dzis"];

            $client->getUserNotestore()->updateNote($client->getToken(), $note);
        }
    }
}
