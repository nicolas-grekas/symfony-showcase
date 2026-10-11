<?php

namespace App\Tests\Showcase;

use App\Showcase\Feature;
use App\Showcase\FeatureCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FeatureCollectorTest extends TestCase
{
    public function testTheFeaturesOfTheRouteAndOfTheResponseAreListed()
    {
        $collector = new FeatureCollector(\dirname(__DIR__, 2));
        $collector->collect(new Request(attributes: ['_route' => 'ticket_buy']), new Response(headers: ['Link-Template' => '</api/conferences/{slug}>; rel="item"']));

        $used = array_column($collector->getUsed(), 'id');
        $this->assertContains(Feature::LockAttribute->value, $used);
        $this->assertContains(Feature::LinkTemplate->value, $used);
        $this->assertNotContains(Feature::BoundsType->value, $used);
        $this->assertContains(Feature::ComponentBundles->value, array_column($collector->getAppWide(), 'id'));
    }

    public function testTheCodeOfEachFeatureIsFound()
    {
        foreach (Feature::cases() as $feature) {
            foreach ($feature->details()->code as [$path, $text]) {
                $this->assertStringContainsString($text, file_get_contents(\dirname(__DIR__, 2).'/'.$path), \sprintf('The code of the "%s" feature is not found in "%s".', $feature->value, $path));
            }
        }
    }
}
