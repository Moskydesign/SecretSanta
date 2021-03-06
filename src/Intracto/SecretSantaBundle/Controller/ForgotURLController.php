<?php

namespace Intracto\SecretSantaBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Intracto\SecretSantaBundle\Form\Type\ForgotLinkType;

class ForgotURLController extends Controller
{
    /**
     * @Route("/forgot-link", name="forgot_url")
     * @Template("IntractoSecretSantaBundle:Party:forgotLink.html.twig")
     */
    public function indexAction()
    {
        $form = $this->createForm(
            ForgotLinkType::class,
            null,
            [
                'action' => $this->generateUrl('resend_url'),
            ]
        );

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * @Route("/resend-management-url", name="resend_url")
     * @Method("POST")
     * @Template("IntractoSecretSantaBundle:Party:forgotLink.html.twig")
     */
    public function resendAction(Request $request)
    {
        $form = $this->createForm(
            ForgotLinkType::class,
            null,
            [
                'action' => $this->generateUrl('resend_url'),
            ]
        );

        $form->handleRequest($request);
        if ($form->isValid()) {
            if ($this->get('intracto_secret_santa.mailer')->sendForgotLinkMail($form->getData()['email'])) {
                $feedback = [
                    'type' => 'success',
                    'message' => $this->get('translator')->trans('flashes.forgot_url.success'),
                ];
            } else {
                $feedback = [
                    'type' => 'danger',
                    'message' => $this->get('translator')->trans('flashes.forgot_url.error'),
                ];
            }

            $this->addFlash($feedback['type'], $feedback['message']);
        }

        return [
            'form' => $form->createView(),
        ];
    }
}
