<?php

namespace Intracto\SecretSantaBundle\Controller\Party;

use Intracto\SecretSantaBundle\Entity\Party;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Intracto\SecretSantaBundle\Entity\Participant;
use Intracto\SecretSantaBundle\Form\Type\AddParticipantType;
use Intracto\SecretSantaBundle\Form\Type\UpdatePartyDetailsType;
use Intracto\SecretSantaBundle\Form\Type\PartyExcludeParticipantType;

class ManagementController extends Controller
{
    /**
     * @Route("/manage/{listUrl}", name="party_manage")
     * @Template("IntractoSecretSantaBundle:Party/manage:valid.html.twig")
     */
    public function validAction($listUrl, Form $excludeForm = null)
    {
        /** @var \Intracto\SecretSantaBundle\Entity\Party $party */
        $party = $this->getParty($listUrl);

        $addParticipantForm = $this->createForm(
            AddParticipantType::class,
            new Participant(),
            [
                'action' => $this->generateUrl(
                    'party_manage_addParticipant',
                    ['listUrl' => $listUrl]
                ),
            ]
        );
        $updatePartyDetailsForm = $this->createForm(
            UpdatePartyDetailsType::class,
            $party,
            [
                'action' => $this->generateUrl(
                    'party_manage_update',
                    ['listUrl' => $listUrl]
                ),
            ]
        );

        // We wrap the admin's message into our own message and from 19/apr/2017 we no longer save
        // our own message in the DB. We don't support older parties to prevent the message from occuring twice.
        if ($party->getCreated() || $party->getCreationDate() < new \DateTime('2017-04-20')) {
            $updatePartyDetailsForm->remove('message');
        }

        if ($party->getEventdate() < new \DateTime('-2 years')) {
            return $this->render('IntractoSecretSantaBundle:Party/manage:expired.html.twig', [
                'party' => $party,
                'delete_party_csrf_token' => $this->get('security.csrf.token_manager')->getToken('delete_party'),
            ]);
        }
        if ($excludeForm === null) {
            $excludeForm = $this->createForm(PartyExcludeParticipantType::class, $party,
                [
                    'action' => $this->generateUrl('party_exclude', ['listUrl' => $listUrl]),
                ]
            );
        }

        return [
            'addParticipantForm' => $addParticipantForm->createView(),
            'updatePartyDetailsForm' => $updatePartyDetailsForm->createView(),
            'party' => $party,
            'delete_party_csrf_token' => $this->get('security.csrf.token_manager')->getToken('delete_party'),
            'delete_participant_csrf_token' => $this->get('security.csrf.token_manager')->getToken('delete_participant'),
            'excludeForm' => $excludeForm->createView(),
        ];
    }

    /**
     * @Route("/manage/update/{listUrl}", name="party_manage_update")
     * @Method("POST")
     */
    public function updateAction(Request $request, $listUrl)
    {
        /** @var \Intracto\SecretSantaBundle\Entity\Party $party */
        $party = $this->getParty($listUrl);

        $updatePartyDetailsForm = $this->createForm(UpdatePartyDetailsType::class, $party);
        $updatePartyDetailsForm->handleRequest($request);

        if ($updatePartyDetailsForm->isSubmitted() && $updatePartyDetailsForm->isValid()) {
            $this->get('doctrine.orm.entity_manager')->persist($party);
            $this->get('doctrine.orm.entity_manager')->flush();

            if ($party->getCreated()) {
                $this->get('intracto_secret_santa.mailer')->sendPartyUpdatedMailsForParty($party);
            }

            $this->get('session')->getFlashBag()->add(
                'success',
                $this->get('translator')->trans('flashes.management.updated_party.success')
            );
        } else {
            $this->get('session')->getFlashBag()->add(
                'danger',
                $this->get('translator')->trans('flashes.management.updated_party.danger')
            );
        }

        return $this->redirect($this->generateUrl('party_manage', ['listUrl' => $listUrl]));
    }

    /**
     * @Route("/manage/addParticipant/{listUrl}", name="party_manage_addParticipant")
     * @Method("POST")
     */
    public function addParticipantAction(Request $request, $listUrl)
    {
        /** @var \Intracto\SecretSantaBundle\Entity\Party $party */
        $party = $this->getParty($listUrl);

        $newParticipant = new Participant();
        $addParticipantForm = $this->createForm(AddParticipantType::class, $newParticipant);
        $addParticipantForm->handleRequest($request);

        if ($addParticipantForm->isSubmitted() && $addParticipantForm->isValid()) {
            $newParticipant->setParty($party);

            if ($party->getCreated()) {
                $newParticipant->setUrl(base_convert(sha1(uniqid(mt_rand(), true)), 16, 36));
                $this->get('doctrine.orm.entity_manager')->persist($newParticipant);
                $this->get('doctrine.orm.entity_manager')->flush($newParticipant);

                $adminId = $this->get('intracto_secret_santa.query.participant_report')->findAdminIdByPartyId($party->getId());
                /** @var Participant $admin */
                $admin = $this->get('intracto_secret_santa.repository.participant')->findOneById($adminId[0]['id']);
                $adminMatch = $admin->getAssignedParticipant();

                $this->get('doctrine.orm.entity_manager')->persist($admin);
                $this->get('doctrine.orm.entity_manager')->flush($admin);

                $admin->setAssignedParticipant($newParticipant);
                $this->get('doctrine.orm.entity_manager')->persist($admin);
                $this->get('doctrine.orm.entity_manager')->flush($admin);

                $newParticipant->setAssignedParticipant($adminMatch);
                $this->get('doctrine.orm.entity_manager')->persist($newParticipant);
                $this->get('doctrine.orm.entity_manager')->flush();

                $this->get('intracto_secret_santa.mailer')->sendSecretSantaMailForParticipant($admin);
                $this->get('intracto_secret_santa.mailer')->sendSecretSantaMailForParticipant($newParticipant);
            } else {
                $this->get('doctrine.orm.entity_manager')->persist($newParticipant);
                $this->get('doctrine.orm.entity_manager')->flush();
            }

            $this->get('session')->getFlashBag()->add(
                'success',
                $this->get('translator')->trans('flashes.management.add_participant.success')
            );
        } else {
            $this->get('session')->getFlashBag()->add(
                'danger',
                $this->get('translator')->trans('flashes.management.add_participant.danger')
            );
        }

        return $this->redirect($this->generateUrl('party_manage', ['listUrl' => $listUrl]));
    }

    /**
     * @Route("/manage/start/{listUrl}", name="party_manage_start")
     * @Method("GET")
     */
    public function startPartyAction($listUrl)
    {
        /** @var Party $party */
        $party = $this->getParty($listUrl);

        if ($party->getCreated() || $party->getParticipants()->count() < 3) {
            $this->get('session')->getFlashBag()->add(
                'danger',
                $this->get('translator')->trans('flashes.management.start_party.danger')
            );

            return $this->redirect($this->generateUrl('party_manage', ['listUrl' => $party->getListurl()]));
        }

        $mailerService = $this->get('intracto_secret_santa.mailer');

        $party->setCreated(true);
        $this->get('doctrine.orm.entity_manager')->persist($party);

        $this->get('intracto_secret_santa.service.participant')->shuffleParticipants($party);

        $this->get('doctrine.orm.entity_manager')->flush();

        $mailerService->sendSecretSantaMailsForParty($party);

        $this->get('session')->getFlashBag()->add(
            'success',
            $this->get('translator')->trans('flashes.management.start_party.success')
        );

        return $this->redirect($this->generateUrl('party_manage', ['listUrl' => $party->getListurl()]));
    }

    /**
     * @Route("/exclude/{listUrl}", name="party_exclude")
     */
    public function excludeAction(Request $request, $listUrl)
    {
        /** @var MailerService $mailerService */
        $party = $this->getParty($listUrl);

        if (count($party->getParticipants()) <= 3) {
            $this->get('session')->getFlashBag()->add(
                'danger',
                $this->get('translator')->trans('party_manage_valid.excludes.not_enough')
            );

            return $this->redirect($this->generateUrl('party_manage', ['listUrl' => $party->getListurl()]));
        }

        $form = $this->createForm(PartyExcludeParticipantType::class, $party);
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $this->get('doctrine.orm.entity_manager')->persist($party);
                $this->get('doctrine.orm.entity_manager')->flush();
                $this->get('session')->getFlashBag()->add(
                    'success',
                    $this->get('translator')->trans('flashes.management.excludes.success')
                );
            } else {
                return $this->forward('IntractoSecretSantaBundle:Party/Management:valid', array('listUrl' => $listUrl, 'excludeForm' => $form));
            }
        }

        return $this->redirect($this->generateUrl('party_manage', ['listUrl' => $party->getListurl()]));
    }

    private function getParty($listUrl)
    {
        /** @var \Intracto\SecretSantaBundle\Entity\PartyRepository $party */
        $party = $this->get('intracto_secret_santa.repository.party')->findOneByListurl($listUrl);
        if ($party === null) {
            throw new NotFoundHttpException();
        }

        return $party;
    }
}
