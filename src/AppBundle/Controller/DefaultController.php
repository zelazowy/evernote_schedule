<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Auth;
use EDAM\NoteStore\NoteFilter;
use EDAM\NoteStore\NoteList;
use EDAM\NoteStore\NotesMetadataResultSpec;
use Evernote\AdvancedClient;
use Evernote\Auth\OauthHandler;
use Evernote\Client;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction()
    {
        $auth = $this->getDoctrine()->getRepository(Auth::class)->find(1);
        if (true === $auth instanceof Auth) {
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
        $nFilter->words = "reminderTime:*";
        $nFilter->order = "created";
        $rSpec = new NotesMetadataResultSpec();
        $rSpec->includeTitle = true;
        $rSpec->includeAttributes = true;
        $rSpec->includeTitle = true;

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

        return $this->render(":default:schedule.html.twig", ["notes" => $notes]);
    }
}
