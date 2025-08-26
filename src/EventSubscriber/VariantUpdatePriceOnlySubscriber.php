<?php

namespace App\EventSubscriber;

use Endertech\EcommerceMiddleware\Contracts\Model\VariantInterface;
use Endertech\EcommerceMiddleware\Core\Decorator\DecoratorInspector;
use Endertech\EcommerceMiddleware\Core\Task\DestinationToSource\InventoryUpdate;
use Endertech\EcommerceMiddleware\Core\Task\DestinationToSource\VariantUpdate;
use Endertech\EcommerceMiddlewareEvents\Core\Data\Inventory\DataMiddleware\OnStartInventoryUpdateEvent;
use Endertech\EcommerceMiddlewareEvents\Core\Data\Variant\DataMiddleware\HandleSuccessfulVariantUpdateEvent;
use Endertech\EcommerceMiddlewareEvents\Core\Data\Variant\DataMiddleware\OnStartVariantUpdateEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class VariantUpdatePriceOnlySubscriber implements EventSubscriberInterface
{
    private $useVariantUpdatePriceOnly = false;

    public function setUseVariantUpdatePriceOnlyEnabled($enabled = null)
    {
        $this->useVariantUpdatePriceOnly = (bool) $enabled;

        return $this;
    }

    public function isUseVariantUpdatePriceOnlyEnabled()
    {
        return $this->useVariantUpdatePriceOnly;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            OnStartVariantUpdateEvent::class => ['onStartVariantUpdateForVariantUpdatePriceOnly', -1000], // must be after other listeners
            OnStartInventoryUpdateEvent::class => ['onStartInventoryUpdateForVariantUpdatePriceOnly', -1000], // must be after other listeners
            HandleSuccessfulVariantUpdateEvent::class => ['onHandleSuccessfulVariantUpdateIgnoredForVariantUpdatePriceOnly', 1000], // must be before other listeners
        ];
    }

    public function onStartVariantUpdateForVariantUpdatePriceOnly(OnStartVariantUpdateEvent $event)
    {
        $task = $event->getLoggable();
        if (!$task instanceof VariantUpdate) {
            return;
        }

        if ($task->getOption('quiet') && $task->getOption('verbose')) {
            // the "quiet" and "verbose" options together allows price only functionality to be triggered
            $this->setUseVariantUpdatePriceOnlyEnabled(true);
        }

        if (!$this->isUseVariantUpdatePriceOnlyEnabled()) {
            return;
        }

        $dataMiddleware = $task->getDataMiddleware();
        if (!DecoratorInspector::methodExists($dataMiddleware, 'getEventDispatcher')) {
            return;
        }

        $eventDispatcher = $dataMiddleware->getEventDispatcher();
        if ($eventDispatcher instanceof EventDispatcherInterface) {
            $eventListeners = $eventDispatcher->getListeners();
            foreach ($eventListeners as $eventName => $listeners) {
                for ($i = 0; $i < count($listeners); ++$i) {
                    if (is_array($listeners[$i])
                        && isset($listeners[$i][0])
                        && $listeners[$i][0] instanceof InventorySubscriber
                    ) {
                        // removing specific InventorySubscriber listeners to limit API calls
                        $eventDispatcher->removeListener($eventName, $listeners[$i]);
                    }
                }
            }
        }
    }

    public function onStartInventoryUpdateForVariantUpdatePriceOnly(OnStartInventoryUpdateEvent $event)
    {
        if (!$this->isUseVariantUpdatePriceOnlyEnabled()) {
            return;
        }

        $task = $event->getLoggable();
        if (!$task instanceof InventoryUpdate) {
            return;
        }

        if (!$task->getIsSubtask()) {
            // this is expected to be a subtask of the VariantUpdate task
            return;
        }

        $variant = $task->getInventoryVariant();
        if (!$variant instanceof VariantInterface) {
            return;
        }

        // remove the InventoryUpdate inventoryVariant value to skip any inventory changes
        // @see \Endertech\EcommerceMiddleware\Core\Task\DestinationToSource\InventoryUpdate::doRetrieveResults()
        //$task->setInventoryVariant(null);  // this doesn't allow null to be set
        $refl = new \ReflectionProperty($task, 'inventoryVariant');
        $refl->setAccessible(true);
        $refl->setValue($task, null);
    }

    public function onHandleSuccessfulVariantUpdateIgnoredForVariantUpdatePriceOnly(HandleSuccessfulVariantUpdateEvent $event)
    {
        if (!$this->isUseVariantUpdatePriceOnlyEnabled()) {
            return;
        }

        // prevent other listeners in this event from being triggered after this one
        $event->stopPropagation();
    }
}
