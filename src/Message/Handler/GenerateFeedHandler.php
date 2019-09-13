<?php

declare(strict_types=1);

namespace Setono\SyliusFeedPlugin\Message\Handler;

use Safe\Exceptions\StringsException;
use function Safe\sprintf;
use Setono\SyliusFeedPlugin\FeedType\FeedTypeInterface;
use Setono\SyliusFeedPlugin\Message\Command\GenerateBatch;
use Setono\SyliusFeedPlugin\Message\Command\GenerateFeed;
use Setono\SyliusFeedPlugin\Model\FeedInterface;
use Setono\SyliusFeedPlugin\Registry\FeedTypeRegistryInterface;
use Setono\SyliusFeedPlugin\Repository\FeedRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

final class GenerateFeedHandler implements MessageHandlerInterface
{
    use GetChannelTrait;
    use GetFeedTrait;
    use GetLocaleTrait;

    /** @var FeedTypeRegistryInterface */
    private $feedTypeRegistry;

    /** @var MessageBusInterface */
    private $commandBus;

    public function __construct(
        FeedRepositoryInterface $feedRepository,
        ChannelRepositoryInterface $channelRepository,
        RepositoryInterface $localeRepository,
        FeedTypeRegistryInterface $feedTypeRegistry,
        MessageBusInterface $commandBus
    ) {
        $this->feedRepository = $feedRepository;
        $this->channelRepository = $channelRepository;
        $this->localeRepository = $localeRepository;
        $this->feedTypeRegistry = $feedTypeRegistry;
        $this->commandBus = $commandBus;
    }

    /**
     * @throws Throwable
     */
    public function __invoke(GenerateFeed $message): void
    {
        $feed = $this->getFeed($message->getFeedId());
        $channel = $this->getChannel($message->getChannelId());
        $locale = $this->getLocale($message->getLocaleId());
        $feedType = $this->getFeedType($feed);
        $dataProvider = $feedType->getDataProvider();

        $this->feedRepository->incrementBatches($feed, $dataProvider->getBatchCount($channel, $locale));
        $batches = $dataProvider->getBatches($channel, $locale);

        foreach ($batches as $batch) {
            $this->commandBus->dispatch(new GenerateBatch($feed, $channel, $locale, $batch));
        }
    }

    /**
     * @throws StringsException
     */
    private function getFeedType(FeedInterface $feed): FeedTypeInterface
    {
        if (!$this->feedTypeRegistry->has($feed->getFeedType())) {
            throw new UnrecoverableMessageHandlingException(sprintf('Feed type with code "%s" does not exist', $feed->getFeedType()));
        }

        return $this->feedTypeRegistry->get($feed->getFeedType());
    }
}
