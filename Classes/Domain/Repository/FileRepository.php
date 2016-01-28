<?php
namespace WebVision\WvFileCleanup\Domain\Repository;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use WebVision\WvFileCleanup\FileFacade;

/**
 * Class FileRepository
 *
 * @author Frans Saris <t3ext@beech.it>
 */
class FileRepository
{
    /**
     * Find all unused files
     *
     * @param Folder $folder
     * @param bool $recursive
     * @return \WebVision\WvFileCleanup\FileFacade[]
     */
    public function findUnusedFile(Folder $folder, $recursive = true)
    {
        $return = [];
        $files = $folder->getFiles(0, 0, Folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, $recursive);

        // filer out all files in _recycler_ and _processed_ folder
        $files = array_filter($files, function (FileInterface $file) {
            return $file->getParentFolder()->getName() !== '_recycler_' && !($file instanceof ProcessedFile);
        });

        // filter out all files with references
        $files = array_filter($files, function (File $file) {
            return $this->getReferenceCount($file) === 0;
        });

        foreach ($files as $file) {
            $return[] = new FileFacade($file);
        }

        return $return;
    }

    /**
     * Get count of current references
     *
     * @param File $file
     * @return int
     */
    public function getReferenceCount(File $file)
    {
        // sys_refindex
        $refIndexCount = $this->getDatabaseConnection()->exec_SELECTcountRows(
            'recuid',
            'sys_refindex',
            'ref_table=\'sys_file\''
            . ' AND ref_uid=' . (int)$file->getUid()
            . ' AND deleted=0'
            . ' AND tablename != \'sys_file_metadata\''
        );

        // sys_file_reference
        $fileReferenceCount = $this->getDatabaseConnection()->exec_SELECTcountRows(
            'uid',
            'sys_file_reference',
            'table_local=\'sys_file\''
            . ' AND uid_local=' . (int)$file->getUid()
            . ' AND deleted=0'
        );

        return max((int)$refIndexCount, (int)$fileReferenceCount);
    }

    /**
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }
}
