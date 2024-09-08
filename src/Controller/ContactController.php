<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ContactController extends AbstractController
{
    public function __construct(
        private MailerInterface $mailer
    ){}
    
    #[Route('/contact', name: 'app_contact', priority: 10)]
    public function index(Request $request): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $email = (new TemplatedEmail())
                ->from($form->get('Email')->getData())
                ->to('partnair@justfocus.info')
                ->subject($form->get('Sujet')->getData())
                ->htmlTemplate('emails/contact.html.twig')
                ->context([
                    'Nom' => $form->get('Nom')->getData(),
                    'Message' => $form->get('Message')->getData(),
                ]);
            ;

            $this->mailer->send($email);

            $this->addFlash('success', 'Your email has been sent!');

            $this->redirectToRoute('app_contact', [], 301);

        }

        
        return $this->render('contact/index.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
