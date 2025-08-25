<?php
namespace App\Controller;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

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
    public function __invoke(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $signatureHeader = $request->headers->get('x-hubspot-signature-v3');
            $timestampHeader = $request->headers->get('x-hubspot-request-timestamp');

            $method = $request->getMethod(); // "POST"
            $uri = $request->getUri();       // full URL: https://yourdomain.com/webhook/hubspot/contact
            $body = $request->getContent();

            // HubSpot requires: method + uri + body + timestamp
            $rawString = $method . $uri . $body . $timestampHeader;

            // Compute HMAC-SHA256 and Base64 encode
            $calculatedSignature = base64_encode(
                hash_hmac('sha256', $rawString, $this->hubspotSecret, true) // true => raw output
            );

            // Compare
            if (!hash_equals($calculatedSignature, $signatureHeader)) {
                file_put_contents(
                    __DIR__ . '/../../var/log/hubspot_invalid_signature.log',
                    "Invalid signature:\nGot: {$signatureHeader}\nExpected: {$calculatedSignature}\n\n",
                    FILE_APPEND
                );
                return new JsonResponse(['error' => 'Invalid signature'], 403);
            }

            // ✅ Signature is valid — process webhook
            $data = json_decode($body, true);

            // Example: log the payload
            file_put_contents(
                __DIR__ . '/../../var/log/hubspot_webhook.log',
                print_r($data, true) . "\n\n",
                FILE_APPEND
            );


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
