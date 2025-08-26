<?php

namespace App\Command;

use App\EcommerceMiddleware\ShopifyStoris\Task\DestinationToSource\LocationDetailsUpdate;
use Endertech\EcommerceMiddlewareBundle\Command\BaseTaskCommand;

class MiddlewareDestinationToSourceLocationDetailsUpdateCommand extends BaseTaskCommand
{
    /**
     * @required
     */
    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setLocalTask(LocationDetailsUpdate $task)
    {
        $this->setTask($task);

        return $this;
    }

    protected function configure(): void
    {
        parent::configure();

        if (!$this->getName()) {
            $this->setName('middleware:destination-to-source:location-details-update');
        }

        $this->setDescription('Update Location Details');
    }
}
