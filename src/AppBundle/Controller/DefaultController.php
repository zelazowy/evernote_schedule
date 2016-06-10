<?php

namespace AppBundle\Controller;

use EDAM\NoteStore\NoteFilter;
use EDAM\NoteStore\NoteList;
use EDAM\NoteStore\NoteMetadata;
use EDAM\NoteStore\NotesMetadataList;
use EDAM\NoteStore\NotesMetadataResultSpec;
use EDAM\Types\Note;
use Evernote\AdvancedClient;
use Evernote\Auth\OauthHandler;
use Evernote\Client;
use Evernote\Model\Notebook;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $oauthHandler = new OauthHandler(true);
        $key = $this->getParameter("en_key");
        $secret = $this->getParameter("en_secret");

//        $oauthData = $oauthHandler->authorize($key, $secret, "vendor/evernote/evernote-cloud-sdk-php/sample/oauth/index.php");

//        $this->get("session")->set("en_token", $oauthData['oauth_token']);

        // replace this example code with whatever you need
        return $this->redirectToRoute("schedule");
    }

    /**
     * @Route("/schedule/", name="schedule")
     * @Template(":default:schedule.html.twig")
     *
     * @param Request $request
     *
     * @return array
     */
    public function scheduleAction(Request $request)
    {
        $token = $this->get("session")->get("en_token");

        $advancedClient = new AdvancedClient($token, true);

        $client = new Client($token, true, $advancedClient);


        $nFilter = new NoteFilter();
        $nFilter->words = "reminderTime:*";
        $nFilter->order = "created";
        $rSpec = new NotesMetadataResultSpec();
        $rSpec->includeTitle = true;
        $rSpec->includeAttributes = true;
//        $rSpec->includeTitle = true;

        $notes = $client->getUserNotestore()->findNotesMetadata($token, $nFilter, 0, 50, $rSpec);

//        $filter = new NoteFilter();
//        $filter->notebookGuid = "03db77d0-8e9d-41a3-8666-ad3b303df946";
//
//        $notes = new NoteMetadata($filter);

        /** @var NoteList $notes */
//        $notes = $client->getUserNotestore()->findNotes($token, $filter, 0, 200);

        $notes = (array) $notes->notes;

        usort($notes, function($a, $b) {
            if ($a->attributes->reminderTime == $b->attributes->reminderTime) {
                return 0;
            }

            return $a->attributes->reminderTime > $b->attributes->reminderTime ? 1 : -1;
        });

        return ["notes" => $notes];
    }
}
