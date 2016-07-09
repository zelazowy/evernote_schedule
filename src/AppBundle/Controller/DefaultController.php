<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Auth;
use AppBundle\Form\Type\NoteType;
use EDAM\Error\EDAMNotFoundException;
use EDAM\NoteStore\NoteFilter;
use EDAM\NoteStore\NoteList;
use EDAM\NoteStore\NoteMetadata;
use EDAM\NoteStore\NotesMetadataResultSpec;
use EDAM\Types\Tag;
use Evernote\AdvancedClient;
use Evernote\Auth\OauthHandler;
use Evernote\Client;
use Evernote\Model\Note;
use Evernote\Model\PlainTextNoteContent;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DefaultController extends Controller
{
    /**
     * @Route("/{id}", defaults={"id" = 0}, requirements={"id" = "\d+"}, name="homepage")
     */
    public function indexAction($id = null)
    {
        $auth = $this->getDoctrine()->getRepository(Auth::class)->find($id);
        if (true === $auth instanceof Auth) {
            $this->get("session")->set("en_token", $auth->getToken());

            return $this->redirectToRoute("schedule");
        }

        $key = $this->getParameter("en_key");
        $secret = $this->getParameter("en_secret");

        $oauthHandler = new OauthHandler(true, false, false);
        $tokenData = $oauthHandler->authorize($key, $secret, $this->generateUrl("homepage", [], UrlGeneratorInterface::ABSOLUTE_URL));

        if (false === empty($tokenData)) {
            $token = $tokenData["oauth_token"];
            $this->get("session")->set("en_token", $token);

            $em = $this->getDoctrine()->getEntityManager();

            $auth = new Auth();
            $auth
                ->setToken($token)
                ->setCreatedAt(new \DateTime());

            $em->persist($auth);
            $em->flush();

            return $this->redirectToRoute("schedule");
        }

        exit;
    }

    /**
     * @Route("/schedule/", name="schedule")
     *
     * @param Request $request
     *
     * @return array
     */
    public function scheduleAction()
    {
        $token = $this->get("session")->get("en_token");

        $advancedClient = new AdvancedClient($token, true);

        $client = new Client($token, true, $advancedClient);

        $nFilter = new NoteFilter();
        $nFilter->words = "reminderOrder:* reminderTime:* -reminderDoneTime:*";
        $nFilter->order = "created";
        $rSpec = new NotesMetadataResultSpec();
        $rSpec->includeTitle = true;
        $rSpec->includeAttributes = true;
        $rSpec->includeTitle = true;
        $rSpec->includeTagGuids = true;

        try {
            $noteList = $client->getUserNotestore()->findNotesMetadata($client->getToken(), $nFilter, 0, 50, $rSpec);
        } catch (\Exception $e) {
            $t = $e;
        }

        /** @var NoteList $noteList */
        $notes = (array) $noteList->notes;

        usort($notes, function($a, $b) {
            if ($a->attributes->reminderTime == $b->attributes->reminderTime) {
                return 0;
            }

            return $a->attributes->reminderTime > $b->attributes->reminderTime ? 1 : -1;
        });

        $tagsRaw = $client->getUserNotestore()->listTags($client->getToken());

        $tags = [];
        /** @var Tag $tag */
        foreach ($tagsRaw as $tag) {
            $tags[$tag->guid] = $tag;
        }

        $noteType = $this->createForm(NoteType::class);

        return $this->render(
            ":default:schedule.html.twig",
            [
                "notes" => $notes,
                "tags" => $tags,
                "noteForm" => $noteType->createView(),
            ]
        );
    }

    /**
     * @Route("/done/{noteId}/", name="done")
     *
     * @param $noteId
     *
     * @return RedirectResponse
     */
    public function doneAction($noteId)
    {
        $token = $this->get("session")->get("en_token");

        $advancedClient = new AdvancedClient($token, true);

        $client = new Client($token, true, $advancedClient);

        $note = $client->getNote($noteId);
        $doneNote = clone $note;
        $doneNote->setAsDone();

        $client->replaceNote($note, $doneNote);

        return $this->redirectToRoute("schedule");
    }

    /**
     * @Route("/new/", name="new_reminder")
     *
     * @param Request $request
     *
     * @return RedirectResponse
     * @throws EDAMNotFoundException
     * @throws \Exception
     */
    public function newReminder(Request $request)
    {
        $noteType = $this->createForm(NoteType::class);

        $noteType->handleRequest($request);
        if (true === $noteType->isValid()) {
            $note = new Note();
            $note->setTitle($noteType->get("title")->getData());
            $note->setContent(new PlainTextNoteContent($noteType->get("content")->getData()));
            $note->setReminder((new \DateTime($noteType->get("reminder")->getData()))->getTimestamp());

            $token = $this->get("session")->get("en_token");

            $advancedClient = new AdvancedClient($token, true);

            $client = new Client($token, true, $advancedClient);

            $client->uploadNote($note);
        }

        return $this->redirectToRoute("schedule");
    }

    /**
     * @Route("/tag", name="tag")
     *
     * @return RedirectResponse
     */
    public function tagAction()
    {
        $token = $this->get("session")->get("en_token");

        $advancedClient = new AdvancedClient($token, true);

        $client = new Client($token, true, $advancedClient);

        $nFilter = new NoteFilter();
        $nFilter->words = "reminderOrder:* reminderTime:day -reminderDoneTime:*";
        $nFilter->order = "created";
        $rSpec = new NotesMetadataResultSpec();

        try {
            $noteList = $client->getUserNotestore()->findNotesMetadata($client->getToken(), $nFilter, 0, 50, $rSpec);
        } catch (\Exception $e) {
            $t = $e;
        }

        $tagsRaw = $client->getUserNotestore()->listTags($client->getToken());
        $tags = [];
        /** @var Tag $tag */
        foreach ($tagsRaw as $tag) {
            $tags[$tag->name] = $tag->guid;
        }

        /** @var NoteList $noteList */
        $notes = (array) $noteList->notes;

        /** @var NoteMetadata[] $notes */
        foreach ($notes as $n) {
            /** @var \EDAM\Types\Note $note */
            $note = $client->getUserNotestore()->getNote($client->getToken(), $n->guid, true, true, true, true);
            $note->tagGuids[] = $tags["dzis"];

            $client->getUserNotestore()->updateNote($client->getToken(), $note);
        }

        return $this->redirectToRoute("schedule");
    }
}
