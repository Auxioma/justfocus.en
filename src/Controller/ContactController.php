<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\Cache;

class ContactController extends AbstractController
{
    public function __construct(
        private MailerInterface $mailer,
    ) {
    }

    #[Route('/contact', name: 'app_contact', priority: 10)]
    #[cache(public: true, expires: '+1 hour')]
    public function index(Request $request): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Retrieve and validate the email address
            $fromEmail = $form->get('Email')->getData();
            if (!is_string($fromEmail) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Invalid email address.');

                return $this->redirectToRoute('app_contact', [], 301);
            }

            // Retrieve and validate the subject
            $subject = $form->get('Sujet')->getData();
            if (!is_string($subject) || empty($subject)) {
                $this->addFlash('error', 'Subject cannot be empty.');

                return $this->redirectToRoute('app_contact', [], 301);
            }

            // Safely handle 'Nom' and 'Message' fields with default empty strings
            $name = $form->get('Nom')->getData() ?? '';        // Default to empty string if null
            $message = $form->get('Message')->getData() ?? ''; // Default to empty string if null

            $email = (new TemplatedEmail())
                ->from(new Address($fromEmail)) // Use Address to ensure valid email format
                ->to('partnair@justfocus.info')
                ->subject($subject) // Ensure it's a valid string
                ->htmlTemplate('emails/contact.html.twig')
                ->context([
                    'Nom' => $name,     // Pass name safely as string
                    'Message' => $message, // Pass message safely as string
                ]);

            $this->mailer->send($email);

            $this->addFlash('success', 'Your email has been sent!');

            return $this->redirectToRoute('app_contact', [], 301);
        }

        $template = $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
        ]);

        $template->headers->set('Cache-Control', 'public, max-age=3600, must-revalidate');

        return $template; 
    }
}
