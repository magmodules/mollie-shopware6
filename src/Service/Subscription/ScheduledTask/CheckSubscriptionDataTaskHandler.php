<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Subscription\ScheduledTask;

use Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Factory\MollieApiFactory;

class CheckSubscriptionDataTaskHandler extends ScheduledTaskHandler
{
    /**
     * @var EntityRepositoryInterface
     */
    private EntityRepositoryInterface $mollieSubscriptionsRepository;

    /**
     * @var MollieApiFactory
     */
    private MollieApiFactory $apiFactory;

    /**
     * @var LoggerService
     */
    private LoggerService $loggerService;

    /**
     * @param EntityRepositoryInterface $scheduledTaskRepository
     * @param EntityRepositoryInterface $mollieSubscriptionsRepository
     * @param MollieApiFactory $apiFactory
     * @param LoggerService $loggerService
     */
    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        EntityRepositoryInterface $mollieSubscriptionsRepository,
        MollieApiFactory $apiFactory,
        LoggerService $loggerService
    ) {
        parent::__construct($scheduledTaskRepository);

        $this->mollieSubscriptionsRepository = $mollieSubscriptionsRepository;
        $this->apiFactory = $apiFactory;
        $this->loggerService = $loggerService;
    }

    /**
     * @return iterable
     */
    public static function getHandledMessages(): iterable
    {
        return [ CheckSubscriptionDataTask::class ];
    }

    /**
     *  Send Prepayment Reminder Email
     * @throws Exception
     */
    public function run(): void
    {
        $mollie = $this->apiFactory->getClient();
        $mollieSubscriptions = $mollie->subscriptions->page();
        $subscriptions = $this->mollieSubscriptionsRepository->search(new Criteria(), Context::createDefaultContext());

        foreach ($subscriptions as $subscription) {
            if ($status = $this->recursive_array_search($subscription->getStatus(),  $mollieSubscriptions)) {
                $this->mollieSubscriptionsRepository->upsert([[
                    'id' => $subscription->getId(),
                    'status' => $status
                ]], Context::createDefaultContext());
            }
        }

    }

    /**
     * @param $needle
     * @param $haystack
     * @return array|false
     */
    private function recursive_array_search($needle, $haystack)
    {
        foreach($haystack as $value) {
            if($needle != $value OR (is_array($value) && $this->recursive_array_search($needle, $value))) {
                return $value;
            }
        }
        return false;
    }
}