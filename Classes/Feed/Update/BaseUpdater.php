<?php

declare(strict_types=1);

namespace Pixelant\PxaSocialFeed\Feed\Update;

use Exception;
use RuntimeException;
use GuzzleHttp\Client;
use InvalidArgumentException;
use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Resource\File;
use GuzzleHttp\Exception\GuzzleException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use Pixelant\PxaSocialFeed\Domain\Model\Feed;
use TYPO3\CMS\Core\Resource\MimeTypeDetector;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Pixelant\PxaSocialFeed\Domain\Model\Configuration;
use Pixelant\PxaSocialFeed\Domain\Model\FileReference;
use Pixelant\PxaSocialFeed\Domain\Repository\FeedRepository;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Extbase\Reflection\Exception\UnknownClassException;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFolderException;
use TYPO3\CMS\Core\Resource\Exception\IllegalFileExtensionException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientUserPermissionsException;
use TYPO3\CMS\Extbase\Reflection\ClassSchema\Exception\NoSuchPropertyException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFileWritePermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderWritePermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;

/**
 * Class BaseUpdater
 */
abstract class BaseUpdater implements FeedUpdaterInterface
{
    /**
     * Keep all processed feed items
     *
     * @var ObjectStorage<Feed>
     */
    protected $feeds;

    protected EventDispatcherInterface $eventDispatcher;
    
    protected MimeTypeDetector $mimeTypeDetector;

    protected FeedRepository $feedRepository;

    /**
     * BaseUpdater constructor.
     */
    public function __construct(
    ) {
        $this->feeds = new ObjectStorage();
        $this->feedRepository = GeneralUtility::makeInstance(FeedRepository::class);
        $this->mimeTypeDetector = GeneralUtility::makeInstance(MimeTypeDetector::class);
        $this->eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    /**
     * Persist changes
     */
    public function persist(): void
    {
        GeneralUtility::makeInstance(PersistenceManagerInterface::class)->persistAll();
    }

    /**
     * Clean all outdated records
     *
     * @param Configuration $configuration
     */
    public function cleanUp(Configuration $configuration): void
    {
        if (count($this->feeds) > 0) {
            /** @var Feed $feedToRemove */
            foreach ($this->feedRepository->findNotInStorage($this->feeds, $configuration) as $feedToRemove) {
                // todo: remove in next major version
                /** @deprecated The call to changedFeedItem is deprecated and will be removed in version 4 */
                $this->getSignalSlotDispatcher()->dispatch(__CLASS__, 'changedFeedItem', [$feedToRemove]);

                $this->getSignalSlotDispatcher()->dispatch(__CLASS__, 'removedFeedItem', [$feedToRemove]);
                $this->feedRepository->remove($feedToRemove);
            }
        }
    }

    /**
     * Add or update feed object.
     * Save all processed items
     *
     * @param Feed $feed
     */
    protected function addOrUpdateFeedItem(Feed $feed): void
    {
        // Check if $feed is new or modified and emit change event
        if ($feed->_isDirty() || $feed->_isNew()) {
            $this->getSignalSlotDispatcher()->dispatch(__CLASS__, 'changedFeedItem', [$feed]);
        }

        $this->feeds->attach($feed);
        $this->feedRepository->{$feed->_isNew() ? 'add' : 'update'}($feed);
    }

    /**
     * Get an existing items from the references that matches the file
     *
     * @param ObjectStorage<FileReference> $items
     *
     * @return bool|FileReference
     */
    protected function checkIfFalRelationIfAlreadyExists(ObjectStorage $items, FileReference $fileReference)
    {
        $reference = false;
        foreach ($items as $item) {
            if ($item->getFileUid() === $fileReference->getFileUid()) {
                $reference = $item;
                break;
            }
        }

        return $reference;
    }

    /**
     * Use json_encode to get emoji character convert to unicode
     * @TODO is there better way to do this ?
     *
     * @param $message
     * @return string
     */
    protected function encodeMessage(string $message): string
    {
        return substr(json_encode($message), 1, -1);
    }

    /**
     * @param string $url
     * @param Feed $feed
     * @return FileReference|null
     * @throws UnknownClassException
     * @throws NoSuchPropertyException
     * @throws InvalidArgumentException
     * @throws InsufficientFolderAccessPermissionsException
     * @throws ExistingTargetFolderException
     * @throws InsufficientFolderWritePermissionsException
     * @throws Exception
     * @throws FileDoesNotExistException
     * @throws GuzzleException
     * @throws IllegalFileExtensionException
     * @throws RuntimeException
     * @throws InsufficientFileWritePermissionsException
     * @throws InsufficientUserPermissionsException
     */
    protected function storeImg(string $url, Feed $feed): ?FileReference
    {
        $extbaseFileReference = null;
        if (empty($url)) {
            return $extbaseFileReference;
        }

        $imageFile = $this->downloadImage($url, $feed->getConfiguration());
        if ($imageFile) {
            $extbaseFileReference = GeneralUtility::makeInstance(FileReference::class);
            $extbaseFileReference->setOriginalFile($imageFile);
        }

        return $extbaseFileReference;
    }

    /**
     * @param string $url
     * @param Configuration $configuration
     * @return File|null
     * @throws InvalidArgumentException
     * @throws DBALException
     * @throws DriverException
     * @throws InsufficientFolderAccessPermissionsException
     * @throws ExistingTargetFolderException
     * @throws InsufficientFolderWritePermissionsException
     * @throws Exception
     * @throws FileDoesNotExistException
     * @throws GuzzleException
     * @throws IllegalFileExtensionException
     * @throws RuntimeException
     * @throws InsufficientFileWritePermissionsException
     * @throws InsufficientUserPermissionsException
     */
    protected function downloadImage(string $url, Configuration $configuration): ?File
    {
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $storage = $resourceFactory->getDefaultStorage();

        $folderPath = 'socialfeed/' . strtolower($configuration->getName()) . '/';
        if (!$storage->hasFolder($folderPath)) {
            $downloadFolder = $storage->createFolder($folderPath);
        } else {
            $downloadFolder = $storage->getFolder($folderPath);
        }

        $baseUrl = explode('?', basename($url), 2);
        $filename = md5($baseUrl[0]);

        $file = $downloadFolder->getFile($filename);
        if ($file == null) {
            $httpClient = GeneralUtility::makeInstance(Client::class);
            $response = $httpClient->get($url);
            if ($response->getStatusCode() === 200) {
                $mimetype = $response->getHeader('Content-Type')[0];
                $fileExtensions =  $this->mimeTypeDetector->getFileExtensionsForMimeType($mimetype);

                $file = $downloadFolder->createFile($filename . (('.' . $fileExtensions[0]) ?? ''));
                $file->setContents($response->getBody()->getContents());
            }
        }

        return $file;
    }
}
