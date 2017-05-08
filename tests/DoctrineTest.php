<?php

/*
 * This file is part of the Eventum (Issue Tracking System) package.
 *
 * @copyright (c) Eventum Team
 * @license GNU General Public License, version 2 or later (GPL-2+)
 *
 * For the full copyright and license information,
 * please see the COPYING and AUTHORS files
 * that were distributed with this source code.
 */

use Eventum\Db\Doctrine;
use Eventum\Model\Entity;

class DoctrineTest extends PHPUnit_Framework_TestCase
{
    public function test1()
    {
        $productRepository = $this->getEntityManager()->getRepository(Eventum\Doctrine\Product::class);
        $products = $productRepository->findAll();

        /**
         * @var Eventum\Doctrine\Product $product
         */
        foreach ($products as $product) {
            echo sprintf("-%s\n", $product->getName());
        }
    }

    public function test2()
    {
        $em = $this->getEntityManager();
        $repo = $em->getRepository(\Eventum\Model\Entity\Commit::class);
        $items = $repo->findBy([], null, 10);

        /**
         * @var \Eventum\Model\Entity\Commit $item
         */
        foreach ($items as $item) {
            echo sprintf("* %s %s\n", $item->getId(), trim($item->getMessage()));
        }
    }

    public function test3()
    {
        $em = $this->getEntityManager();
        $repo = $em->getRepository(\Eventum\Model\Entity\Commit::class);
        $qb = $repo->createQueryBuilder('commit');

        $qb->setMaxResults(10);

        $query = $qb->getQuery();
        $items = $query->getArrayResult();

        print_r($items);
    }

    public function test4()
    {
        $em = $this->getEntityManager();
        $repo = $em->getRepository(\Eventum\Model\Entity\Commit::class);

        $issue_id = 1;
        $changeset = uniqid('z1');
        $ci = Entity\Commit::create()
            ->setScmName('cvs')
            ->setAuthorName('Au Thor')
            ->setCommitDate(Date_Helper::getDateTime())
            ->setChangeset($changeset)
            ->setMessage('Mes-Sage');
        $em->persist($ci);
        $em->flush();

        $cf = Entity\CommitFile::create()
            ->setCommitId($ci->getId())
            ->setFilename('file');
        $em->persist($cf);
        $em->flush();

        $isc = Entity\IssueCommit::create()
            ->setCommitId($ci->getId())
            ->setIssueId($issue_id);
        $em->persist($isc);
        $em->flush();

        printf(
            "ci: %d\ncf: %d\nisc: %d\n",
            $ci->getId(), $cf->getId(), $isc->getId()
        );
    }

    private function getEntityManager()
    {
        return Doctrine::getEntityManager();
    }
}