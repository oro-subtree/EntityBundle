<?php

namespace Oro\Bundle\EntityBundle\Tests\Functional\Controller\Api\Rest;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

/**
 * @outputBuffering enabled
 * @dbIsolation
 */
class EntityDictionaryControllerTest extends WebTestCase
{
    protected function setUp()
    {
        $this->initClient([], $this->generateWsseAuthHeader());
    }

    public function testGetAliases()
    {
        $this->client->request('GET', $this->getUrl('oro_api_get_dictionaries',['entity' => 'entity']));
        $this->getJsonResponseContent($this->client->getResponse(), 200);
    }
}
