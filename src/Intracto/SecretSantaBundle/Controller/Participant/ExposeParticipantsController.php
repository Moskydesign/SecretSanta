<?php

namespace Intracto\SecretSantaBundle\Controller\Participant;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExposeParticipantsController extends Controller
{
    /**
     * @Route("/participants/expose/{listUrl}", name="expose_participants")
     * @Template("IntractoSecretSantaBundle:Participant:exposeAll.html.twig")
     */
    public function indexAction($listUrl)
    {
        /** @var \Intracto\SecretSantaBundle\Entity\PartyRepository $party */
        $party = $this->get('intracto_secret_santa.repository.party')->findOneByListurl($listUrl);
        if ($party === null) {
            throw new NotFoundHttpException();
        }

        return [
            'party' => $party,
        ];
    }
}
