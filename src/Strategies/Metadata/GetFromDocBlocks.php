<?php

namespace Mpociot\ApiDoc\Strategies\Metadata;

use ReflectionClass;
use ReflectionMethod;
use Log;
use Illuminate\Routing\Route;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use Mpociot\ApiDoc\Strategies\Strategy;
use Mpociot\ApiDoc\Tools\RouteDocBlocker;

class GetFromDocBlocks extends Strategy
{
    public function __invoke(Route $route, ReflectionClass $controller, ReflectionMethod $method, array $routeRules, array $context = [])
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($route);
        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = $docBlocks['method'];

        list($routeGroupName, $routeGroupDescription, $routeTitle) = $this->getRouteGroupDescriptionAndTitle($methodDocBlock, $docBlocks['class']);

        return [
                'groupName' => $routeGroupName,
                'groupDescription' => $routeGroupDescription,
                'title' => $routeTitle ?: $methodDocBlock->getShortDescription(),
                'description' => $methodDocBlock->getLongDescription()->getContents(),
                'authenticated' => $this->getAuthStatusFromDocBlock($methodDocBlock->getTags()),
                'superadmin' => $this->getAdminStatusFromDocBlock($methodDocBlock->getTags()),
                'farmer' => $this->getFarmerStatusFromDocBlock($methodDocBlock->getTags()),
                'partner' => $this->getPartnerStatusFromDocBlock($methodDocBlock->getTags()),
                'public' => $this->getPublicStatusFromDocBlock($methodDocBlock->getTags()),
        ];
    }

    /**
     * @param array $tags Tags in the method doc block
     *
     * @return bool
     */
    protected function getPublicStatusFromDocBlock(array $tags)
    {
        $authTag = collect($tags)
            ->first(function ($tag) {
                return $tag instanceof Tag && strtolower($tag->getName()) === 'public';
            });

        return (bool) $authTag;
    }

    /**
     * @param array $tags Tags in the method doc block
     *
     * @return bool
     */
    protected function getPartnerStatusFromDocBlock(array $tags)
    {
        $authTag = collect($tags)
            ->first(function ($tag) {
                return $tag instanceof Tag && strtolower($tag->getName()) === 'partner';
            });

        return (bool) $authTag;
    }

    /**
     * @param array $tags Tags in the method doc block
     *
     * @return bool
     */
    protected function getFarmerStatusFromDocBlock(array $tags)
    {
        $authTag = collect($tags)
            ->first(function ($tag) {
                return $tag instanceof Tag && strtolower($tag->getName()) === 'farmer';
            });

        return (bool) $authTag;
    }

    /**
     * @param array $tags Tags in the method doc block
     *
     * @return bool
     */
    protected function getAdminStatusFromDocBlock(array $tags)
    {
        // Log::info(print_r($tags));
        $adminTag = collect($tags)
            ->first(function ($tag) {
                return $tag instanceof Tag && strtolower($tag->getName()) === 'superadmin';
            });

        return (bool) $adminTag;
    }

    /**
     * @param array $tags Tags in the method doc block
     *
     * @return bool
     */
    protected function getAuthStatusFromDocBlock(array $tags)
    {
    //    Log::info(print_r($tags, true));

       $authTag = collect($tags)
            ->first(function ($tag) {
                return $tag instanceof Tag && strtolower($tag->getName()) === 'authenticated';
            });

        return (bool) $authTag;
    }

    /**
     * @param DocBlock $methodDocBlock
     * @param DocBlock $controllerDocBlock
     *
     * @return array The route group name, the group description, ad the route title
     */
    protected function getRouteGroupDescriptionAndTitle(DocBlock $methodDocBlock, DocBlock $controllerDocBlock)
    {
        // @group tag on the method overrides that on the controller
        if (! empty($methodDocBlock->getTags())) {
            foreach ($methodDocBlock->getTags() as $tag) {
                if ($tag->getName() === 'group') {
                    $routeGroupParts = explode("\n", trim($tag->getContent()));
                    $routeGroupName = array_shift($routeGroupParts);
                    $routeGroupDescription = trim(implode("\n", $routeGroupParts));

                    // If the route has no title (the methodDocBlock's "short description"),
                    // we'll assume the routeGroupDescription is actually the title
                    // Something like this:
                    // /**
                    //   * Fetch cars. <-- This is route title.
                    //   * @group Cars <-- This is group name.
                    //   * APIs for cars. <-- This is group description (not required).
                    //   **/
                    // VS
                    // /**
                    //   * @group Cars <-- This is group name.
                    //   * Fetch cars. <-- This is route title, NOT group description.
                    //   **/

                    // BTW, this is a spaghetti way of doing this.
                    // It shall be refactored soon. Deus vult!💪
                    if (empty($methodDocBlock->getShortDescription())) {
                        return [$routeGroupName, '', $routeGroupDescription];
                    }

                    return [$routeGroupName, $routeGroupDescription, $methodDocBlock->getShortDescription()];
                }
            }
        }

        foreach ($controllerDocBlock->getTags() as $tag) {
            if ($tag->getName() === 'group') {
                $routeGroupParts = explode("\n", trim($tag->getContent()));
                $routeGroupName = array_shift($routeGroupParts);
                $routeGroupDescription = implode("\n", $routeGroupParts);

                return [$routeGroupName, $routeGroupDescription, $methodDocBlock->getShortDescription()];
            }
        }

        return [$this->config->get('default_group'), '', $methodDocBlock->getShortDescription()];
    }
}
