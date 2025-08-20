<?php
namespace App\Controller;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Response;

class HubspotWebhookController extends AbstractController
{
    private HttpClientInterface $client;
    private string $hubspotToken;
    private string $hubspotSecret;

    public function __construct(HttpClientInterface $client, string $hubspotToken, string $hubspotSecret)
    {
        $this->client = $client;
        $this->hubspotToken = $hubspotToken;
        $this->hubspotSecret = $hubspotSecret;
    }

    #[Route('/webhook/hubspot/contact', name: 'hubspot_contact_webhook', methods: ['POST'])]
    public function __invoke(EntityManagerInterface $em): JsonResponse
    {
        try {
            // 🔹 Read headers reliably
            $headers = getallheaders();
            $signature = $headers['X-Hubspot-Signature-V3'] ?? null;
            $timestamp = $headers['X-Hubspot-Request-Timestamp'] ?? null;
            file_put_contents(__DIR__ . '/../../var/log/hubspot_header.log', print_r($signature, true), FILE_APPEND);
            if (!$signature || !$timestamp) {
                return new JsonResponse(['error' => 'Missing signature or timestamp'], 403);
            }

            // 🔹 Read raw request body
            $body = file_get_contents('php://input');

            // Build string to sign: timestamp + "." + body
            $stringToSign = $timestamp . '.' . $body;
            // Compute HMAC-SHA256 and Base64 encode
            $calculatedSignature = base64_encode(hash_hmac('sha256', $stringToSign, $this->hubspotSecret, true));
            // 🔹 Verify signature
            // if (!hash_equals($calculatedSignature, $signature)) {
            //     file_put_contents(
            //         __DIR__ . '/../../var/log/hubspot_invalid_signature.log',
            //         "Invalid signature:\nGot: {$signature}\nExpected: {$calculatedSignature}\nTimestamp: {$timestamp}\nBody (hex): " .$body . "\n\n",
            //         FILE_APPEND
            //     );
            //     return new JsonResponse(['error' => 'Invalid signature'], 403);
            // }

            // 🔹 Decode JSON payload
            $data = json_decode($body, true);
            file_put_contents(__DIR__ . '/../../var/log/hubspot_webhook.log', print_r($data, true), FILE_APPEND);

            if (!$data || !is_array($data)) {
                return new JsonResponse(['error' => 'Invalid payload'], 400);
            }

            // 🔹 Process each contact event
            foreach ($data as $event) {
                $hubspotId = $event['objectId'] ?? null;
                if (!$hubspotId) continue;

                $response = $this->client->request('GET',
                    "https://api.hubapi.com/crm/v3/objects/contacts/{$hubspotId}",
                    [
                        'query' => ['properties' => 'email,firstname,lastname,phone,company'],
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->hubspotToken,
                            'Content-Type' => 'application/json',
                        ],
                    ]
                );

                $contactData = $response->toArray();
                $properties = $contactData['properties'] ?? [];

                $email     = $properties['email'] ?? null;
                $firstname = $properties['firstname'] ?? null;
                $lastname  = $properties['lastname'] ?? null;
                $phone     = $properties['phone'] ?? null;
                $company   = $properties['company'] ?? null;

                $contact = $em->getRepository(Contact::class)->findOneBy(['hubspotId' => $hubspotId]);

                if (!$contact) {
                    $contact = new Contact();
                    $contact->setHubspotId($hubspotId);
                    $contact->setCreatedAt(new \DateTimeImmutable());
                }

                $contact->setEmail($email);
                $contact->setFirstname($firstname);
                $contact->setLastname($lastname);
                $contact->setPhone($phone);
                $contact->setCompany($company);
                $contact->setUpdatedAt(new \DateTimeImmutable());
                $contact->setLastDatabaseSync(new \DateTimeImmutable());

                $em->persist($contact);
            }

            $em->flush();
            return new JsonResponse(['status' => 'ok']);
        } catch (\Throwable $e) {
            file_put_contents(
                __DIR__ . '/../../var/log/hubspot_error.log',
                $e->getMessage() . "\n" . $e->getTraceAsString() . "\n",
                FILE_APPEND
            );
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
