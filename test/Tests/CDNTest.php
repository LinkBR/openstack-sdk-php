<?php
/**
 * @file
 *
 * Unit tests for CDN.
 */
namespace HPCloud\Tests\Storage;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Storage\CDN;

/**
 * @ingroup Tests
 */
class CDNTest extends \HPCloud\Tests\TestCase {

  const TTL = 1234;

  protected function destroyCDNFixture($cdn) {
    $cname = $this->conf('hpcloud.swift.container');
    try {
      $cdn->delete($cname);
    }
    catch (\HPCloud\Exception $e) {
      syslog(LOG_WARNING, $e);
    }
  }

  public function testConstructor() {
    $ident = $this->identity();

    $catalog = $ident->serviceCatalog(CDN::SERVICE_TYPE);
    $token = $ident->token();

    $this->assertNotEmpty($catalog[0]['endpoints'][0]['publicURL']);
    $parts = parse_url($catalog[0]['endpoints'][0]['publicURL']);
    $url = 'https://' . $parts['host'];
    $tenantId = $catalog[0]['endpoints'][0]['tenantId'];

    $cdn = new CDN($token, $url, $tenantId);

    $this->assertInstanceOf('\HPCloud\Storage\CDN', $cdn);

  }

  /**
   * @depends testConstructor
   */
  public function testNewFromServiceCatalog() {
    $ident = $this->identity();
    $token = $ident->token();
    $catalog = $ident->serviceCatalog();

    $cdn = CDN::newFromServiceCatalog($catalog, $token);

    $this->assertInstanceOf('\HPCloud\Storage\CDN', $cdn);

    return $cdn;
  }

  /**
   * @depends testNewFromServiceCatalog
   */
  public function testEnable($cdn) {
    $container = $this->conf('hpcloud.swift.container');

    $this->destroyCDNFixture($cdn);
    $was_created = FALSE;
    $retval = $cdn->enable($container, self::TTL, $was_created);

    $this->assertRegexp('/^http[s]?:\/\//', $retval);
    $this->assertTrue($was_created);

    // Enabling twice STILL returns 201.
    //$was_created = FALSE;
    //$retval = $cdn->enable($container, self::TTL, $was_created);
    //$this->assertFalse($was_created);

    return $cdn;
  }

  /**
   * @depends testEnable
   */
  public function testContainers($cdn) {
    $containerList = $cdn->containers();
    $cname = $this->conf('hpcloud.swift.container');

    $this->assertTrue(is_array($containerList));

    $this->assertGreaterThanOrEqual(1, count($containerList));

    $find = NULL;
    foreach ($containerList as $container) {
      if ($container['name'] == $cname) {
        $find = $container;
      }
    }

    $this->assertNotEmpty($find);
    $this->assertEquals(self::TTL, $find['ttl']);
    $this->assertNotEmpty($find['x-cdn-uri']);
    $this->assertFalse($find['log_retention']);
    $this->assertTrue($find['cdn_enabled']);

    // Test that the URI returned is valid:
    //$res = file_get_contents($find['x-cdn-uri'] . '/' . $cname);
    //$this->assertEquals('Foo', $res);

    return $cdn;
  }

  /**
   * @depends testContainers
   */
  public function testContainer($cdn) {
    $cname = $this->conf('hpcloud.swift.container');
    $properties = $cdn->container($cname);

    //throw new \Exception(print_r($properties, TRUE));

    $this->assertNotEmpty($properties);

    $this->assertEquals(self::TTL, $properties['ttl']);
    $this->assertNotEmpty($properties['x-cdn-uri']);
    $this->assertFalse($properties['log_retention']);
    $this->assertTrue($properties['cdn_enabled']);

    return $cdn;
  }

  /**
   * @depends testContainer
   */
  public function testUpdate($cdn) {
    $cname = $this->conf('hpcloud.swift.container');

    $cdn->update($cname, array('ttl' => '4321'));

    $props = $cdn->container($cname);

    $this->assertEquals('4321', $props['ttl']);

    return $cdn;
  }

  /**
   * @depends testUpdate
   */
  public function testDisable($cdn) {
    $cname = $this->conf('hpcloud.swift.container');

    //$cdn->disable($cname);
    $cdn->update($cname, array('cdn_enabled' => FALSE));

    $props = $cdn->container($cname);
    $this->assertFalse($props['cdn_enabled']);

    return $cdn;
  }

  /**
   * @depends testDisable
   * @group CDN-129
   */
  public function testContainersEnabledOnly($cdn) {
    $cname = $this->conf('hpcloud.swift.container');

    //\HPCloud\Bootstrap::setConfiguration(array('transport.debug' => 1));
    $containers = $cdn->containers(TRUE);

    //throw new \Exception(print_r($containers, TRUE));

    $found = 0;
    foreach ($containers as $container) {
      if ($container['name'] == $cname) {
        ++$found;
      }
    }

    $this->assertEquals(0, $found);

    $containers = $cdn->containers(FALSE);
    $found = 0;
    foreach ($containers as $container) {
      if ($container['name'] == $cname) {
        ++$found;
      }
    }

    $this->assertEquals(1, $found);
    //\HPCloud\Bootstrap::setConfiguration(array('transport.debug' => 0));


  }

  /**
   * @depends testDisable
   */
  public function testDelete($cdn) {
    $cname = $this->conf('hpcloud.swift.container');

    $cdn->delete($cname);

    $containers = $cdn->containers();

    $match = 0;
    foreach ($containers as $container) {
      if ($container['name'] == $cname) {
        ++$match;
      }
    }
    $this->assertEquals(0, $match);
    return $cdn;
  }

}
