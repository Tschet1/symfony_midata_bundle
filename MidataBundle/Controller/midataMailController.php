<?php
/**
 * Created by PhpStorm.
 * User: Jan
 * Date: 12.01.18
 * Time: 00:07
 */

namespace PfadiZytturm\MidataBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use PfadiZytturm\MidataBundle\Service\pbsSchnittstelle;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class midataMailController extends Controller
{
    private $logger;
    private $midata;
    private $user;
    private $mapping;
    private $mailer;
    private $done_view;
    private $attachement_folder;

    public function __construct(ContainerInterface $container, pbsSchnittstelle $midata, TokenStorageInterface $token)
    {
        $this->midata = $midata;
        $this->user = $token->getToken()->getUser();
        if ($container->hasParameter("midata.mail.logger")) {
            $this->logger = $container->get($container->getParameter("midata.mail.logger"));
        }
        $this->mailer = $container->get($container->getParameter("midata.mail.mailer"));
        $this->mapping = $container->getParameter("midata.mail.mapping");
        $this->done_view = $container->getParameter('midata.mail.view.done');

        $this->attachement_folder = $this->getParameter("anhaengeFolder");
    }

    /**
     * @param Request $request
     * @return mixed
     *
     * Controller für das Mail Interface
     */
    public function mailAction(Request $request)
    {
        if ($this->logger !== null) {
            $this->logger->log($this->user->getUsername() . " beginnt eine Mail zu schreiben.");
        }

        //lade gruppen (aus cache wenn möglich)
        $gruppen = $this->midata->getChildren();

        // handle post
        if ($request->isMethod('POST')) {
            // if we are here, the user is actually sending the mail
            // check the input
            $content = $request->request->has('Content') ? html_entity_decode($request->request->get('Content')) : null;
            $gruppe = $request->request->has('Gruppe') ? $request->request->get('Gruppe') : null;
            $filter = $request->request->has('Filter') ? $request->request->get('Filter') : null;
            $betreff = $request->request->has('Betreff') ? $request->request->get('Betreff') : null;
            $untergruppen = $request->request->has('Untergruppen') && $request->request->get('Untergruppen') == "on" ? true : false;

            // check if there is an attachment
            $anhang = null;
            if (isset($_FILES['anhang']) && $_FILES['anhang']['size'] > 0) {
                $anhang = $this->attachement_folder . $_FILES['anhang']['name'];
                mkdir($this->attachement_folder, , 0755, true);
                move_uploaded_file($_FILES['anhang']["tmp_name"], $anhang);
                if ($this->logger !== null) {
                    $this->logger->log($this->getUser()->getUsername() . " hat einen Anhang hochgeladen: " . $anhang);
                }
            }

            // send the mail
            $this->loadReceiversAndSendMail($content, $gruppe, $filter, $betreff, $untergruppen, $anhang);

            // return the info page
            return $this->render($this->done_view);
        }

        return $this->render('@PfadiZytturmMidata/mail.html.twig', array('gruppen' => $gruppen, 'platzhalter' => $this->mapping, 'user' => $this->getUser()->getPfadiname(), 'id' => $this->container->getParameter("midata.groupId")));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * This function is used to give the user live feedback on the receivers of his message
     */
    public function mailInfoAction(Request $request)
    {
        $ajax = json_decode($request->getContent());
        if (!$ajax) {
            return new JsonResponse(array('message' => 'Kommunikationsfehler'), 500);
        }
        if (!isset($ajax->group)) {
            return new JsonResponse(array('message' => 'Gruppe fehlt'), 500);
        }
        $fun = isset($ajax->fun) ? $ajax->fun : 'Alle';

        // create the list of all receivers
        $list = $this->midata->loadMembersOfGroupWithFilter($ajax->group, $fun, $ajax->untergruppen);
        $num = count($list);
        array_walk($list, function (&$person) {
            $person = ($person['nickname'] != "") ? $person['nickname'] : $person['first_name'];
        });

        // return list of receivers
        return new JsonResponse(array('message' => 'Sende Mail an ' . $num . ' Personen. (' . implode(', ', $list) . ')'), 200);
    }


    /**
     * @param string $content The content of the mail that gets sent
     * @param integer $gruppe (midata) Id of group
     * @param string $filter [Alle|Teilnehmer|Leiter] which part of the set of persons should receive the message?
     * @param string $betreff the subject of the mail
     * @param boolean $untergruppen Should child groups be included?
     * @param mixed $anhang attachment of the mail
     */
    private function loadReceiversAndSendMail($content, $gruppe, $filter, $betreff, $untergruppen, $anhang)
    {
        // get a list of the receivers
        $list = $this->midata->loadMembersOfGroupWithFilter($gruppe, $filter, $untergruppen);

        // logging
        if ($this->logger !== null) {
            $this->logger->log($this->user->getUsername() . "versendet eine Mail an Gruppe: $gruppe , Filter: $filter, Untergruppen: $untergruppen");
            $this->logger->log($this->user->getUsername() . "versendet eine Mail an " . strval(count($list)) . " Personen.");
        }

        // send
        if ($this->container->hasParameter('midata.mail.mail_domain')){
            $tx = array($this->user->getPfadiname() . "@" . $this->container->getParameter('midata.mail.mail_domain') => $this->user->getPfadiname());
        } else {
            $tx = array($this->user->getUsername() => $this->user->getUsername());
        }

        // handle each person separately
        foreach ($list as $person) {
            // do the text replacements
            $modifiedContent = $content;
            foreach ($this->mapping as $key => $pl) {
                $repl = isset($person[$key]) ? $person[$key] : "";
                $modifiedContent = preg_replace("/\(\-" . $pl . "\-\)/", $repl, $modifiedContent);
            }
            $modifiedContent = preg_replace('/<br(\s+)?\/?>/i', "\n", $modifiedContent);
            $modifiedContent = preg_replace('/<\/p>/i', "\n", $modifiedContent);

            // send the mail
            $this->mailer->sendMail(
                $this->renderView(
                    '@PfadiZytturmMidata/mail.txt.twig',
                    array(
                        'content' => strip_tags($modifiedContent),
                    )
                ),
                $betreff,
                $person['Versandadressen'],
                $tx,
                $anhang
            );

        }


    }

}
