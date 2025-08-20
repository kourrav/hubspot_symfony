<?php
namespace App\Controller;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
    public function __invoke(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            // 🔹 Step 1: Extract signature
            $signature = $request->headers->get('X-HubSpot-Signature-v3');
            if (!$signature) {
                return new JsonResponse(['error' => 'Missing signature'], Response::HTTP_FORBIDDEN);
            }

            // 🔹 Step 2: Build string to sign
            $method = $request->getMethod();
            $uri = $request->getRequestUri();
            $body = $request->getContent();
            $stringToSign = $method . $uri . $body;

            // 🔹 Step 3: Compute hash
            $calculatedSignature = base64_encode(
                hash_hmac('sha256', $stringToSign, $this->hubspotSecret, true)
            );

            // 🔹 Step 4: Validate
            if (!hash_equals($calculatedSignature, $signature)) {
                file_put_contents(__DIR__.'/../../var/log/hubspot_invalid_signature.log', 
                    "Invalid signature: got {$signature}, expected {$calculatedSignature}\n", FILE_APPEND
                );
                return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_FORBIDDEN);
            }

            // ✅ If valid, continue with your existing logic
            $data = json_decode($request->getContent(), true);
            file_put_contents(__DIR__.'/../../var/log/hubspot_webhook.log', print_r($data, true), FILE_APPEND);

            if (!$data || !is_array($data)) {
                return new JsonResponse(['error' => 'Invalid payload'], 400);
            }

            foreach ($data as $event) {
                $hubspotId = $event['objectId'] ?? null;

                if ($hubspotId) {
                    // 🔹 Fetch contact details from HubSpot API
                    $response = $this->client->request('GET',
                        "https://api.hubapi.com/crm/v3/objects/contacts/{$hubspotId}",
                        [
                            'query' => [
                                'properties' => 'email,firstname,lastname,phone,company',
                            ],
                            'headers' => [
                                'Authorization' => 'Bearer ' . $this->hubspotToken,
                                'Content-Type' => 'application/json',
                            ],
                        ]
                    );

                    $contactData = $response->toArray();
                    file_put_contents(__DIR__.'/../../var/log/hubspot_webhook.log', print_r($contactData, true), FILE_APPEND);
                    $properties  = $contactData['properties'] ?? [];

                    $email     = $properties['email'] ?? null;
                    $firstname = $properties['firstname'] ?? null;
                    $lastname  = $properties['lastname'] ?? null;
                    $phone     = $properties['phone'] ?? null;
                    $company   = $properties['company'] ?? null;

                    // 🔹 Check if contact already exists
                    $contact = $em->getRepository(Contact::class)->findOneBy(['hubspotId' => $hubspotId]);

                    if (!$contact) {
                        // create new
                        $contact = new Contact();
                        $contact->setHubspotId($hubspotId);
                        $contact->setCreatedAt(new \DateTimeImmutable());
                    }

                    // update common fields
                    $contact->setEmail($email);
                    $contact->setFirstname($firstname);
                    $contact->setLastname($lastname);
                    $contact->setPhone($phone);
                    $contact->setCompany($company);
                    $contact->setUpdatedAt(new \DateTimeImmutable());
                    $contact->setLastDatabaseSync(new \DateTimeImmutable());

                    $em->persist($contact);
                }
            }

            $em->flush();
            return new JsonResponse(['status' => 'ok']);
        } catch (\Throwable $e) {
            file_put_contents(__DIR__.'/../../var/log/hubspot_error.log', $e->getMessage() . "\n" . $e->getTraceAsString(), FILE_APPEND);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
