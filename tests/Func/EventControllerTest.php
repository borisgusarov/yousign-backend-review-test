<?php

namespace App\Tests\Func;

use App\DataFixtures\EventFixtures;
use App\Entity\Event;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;

class EventControllerTest extends WebTestCase
{
    protected AbstractDatabaseTool $databaseTool;
    private static $client;

    protected function setUp(): void
    {
        static::$client = static::createClient();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $metaData = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->updateSchema($metaData);

        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $this->databaseTool->loadFixtures(
            [EventFixtures::class]
        );
    }

    private function sendUpdateRequest($eventId, $payload, $headers = ['CONTENT_TYPE' => 'application/json'])
    {
        $client = static::$client;
        $client->request(
            'PUT',
            sprintf('/api/event/%d/update', $eventId),
            [],
            [],
            $headers,
            $payload
        );
        return $client;
    }

    public function testUpdateShouldReturnEmptyResponse()
    {
        $client = $this->sendUpdateRequest(
            EventFixtures::EVENT_1_ID,
            json_encode(['comment' => 'It‘s a test comment !!!!!!!!!!!!!!!!!!!!!!!!!!!'])
        );

        $this->assertResponseStatusCodeSame(204);
        // Assert that the response has no content
        $this->assertEmpty($client->getResponse()->getContent());
    }


    public function testUpdateShouldReturnHttpNotFoundResponse()
    {
        $client = $this->sendUpdateRequest(
            7897897897,
            json_encode(['comment' => 'It‘s a test comment !!!!!!!!!!!!!!!!!!!!!!!!!!!'])
        );

        $this->assertResponseStatusCodeSame(404);

        $expectedJson = json_encode([
            "message" => "Event identified by 7897897897 not found !"
        ]);

        self::assertJsonStringEqualsJsonString($expectedJson, $client->getResponse()->getContent());
    }

    /**
     * @dataProvider providePayloadViolations
     */
    public function testUpdateShouldReturnBadRequest(string $payload, string $expectedResponse)
    {
        $client = $this->sendUpdateRequest(
            EventFixtures::EVENT_1_ID,
            $payload
        );

        self::assertResponseStatusCodeSame(400);
        self::assertJsonStringEqualsJsonString($expectedResponse, $client->getResponse()->getContent());
    }

    public function providePayloadViolations(): iterable
    {
        yield 'comment too short' => [
            json_encode([
                "comment" => "short"
            ]),
            json_encode([
                "message" => "This value is too short. It should have 20 characters or more."
            ])
        ];
    }
}
