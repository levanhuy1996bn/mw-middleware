<?php

namespace App\Command;

use App\EcommerceMiddleware\ShopifyStoris\Task\Middleware\GeneratePowerReviewsProductFile;
use Endertech\EcommerceMiddlewareBundle\Command\BaseTaskCommand;
use Symfony\Component\Console\Input\InputOption;

class MiddlewareMiddlewareGeneratePowerReviewsProductFileCommand extends BaseTaskCommand
{
    /**
     * @required
     */
    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setLocalTask(GeneratePowerReviewsProductFile $task)
    {
        $this->setTask($task);

        return $this;
    }

    protected function configure(): void
    {
        parent::configure();

        if (!$this->getName()) {
            $this->setName('middleware:middleware:generate-power-reviews-product-file');
        }

        $this->setDescription('Generate a Power Reviews file with Product data from Middleware');

        $this->addOption('send-file', null, InputOption::VALUE_NONE, 'Sends the generated file to Power Reviews');
        $this->addOption('pr-environment', null, InputOption::VALUE_REQUIRED, 'The Power Reviews environment to use with the generated file name', 'test');
    }
}
