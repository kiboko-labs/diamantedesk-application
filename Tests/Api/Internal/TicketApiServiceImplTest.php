<?php
/*
 * Copyright (c) 2014 Eltrino LLC (http://eltrino.com)
 *
 * Licensed under the Open Software License (OSL 3.0).
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://opensource.org/licenses/osl-3.0.php
 *
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@eltrino.com so we can send you a copy immediately.
 */

namespace Diamante\DeskBundle\Tests\Api\Internal;

use Diamante\DeskBundle\Api\Command\Filter\FilterTicketsCommand;
use Diamante\DeskBundle\Api\Internal\TicketApiServiceImpl;
use Diamante\DeskBundle\Entity\Branch;
use Diamante\DeskBundle\Entity\Ticket;
use Diamante\DeskBundle\Model\Shared\Filter\FilterPagingProperties;
use Diamante\DeskBundle\Model\Shared\Filter\PagingInfo;
use Diamante\DeskBundle\Model\Ticket\Notifications\NotificationDeliveryManager;
use Diamante\DeskBundle\Model\Ticket\Priority;
use Diamante\DeskBundle\Model\Ticket\Source;
use Diamante\DeskBundle\Model\Ticket\Status;
use Diamante\DeskBundle\Model\Ticket\TicketSequenceNumber;
use Diamante\DeskBundle\Model\Ticket\UniqueId;
use Diamante\DeskBundle\Model\User\User;
use Diamante\DeskBundle\Model\User\UserDetails;
use Eltrino\PHPUnit\MockAnnotations\MockAnnotations;
use Oro\Bundle\UserBundle\Entity\User as OroUser;

class TicketApiServiceImplTest extends \PHPUnit_Framework_TestCase
{
    const SUBJECT      = 'Subject';
    const DESCRIPTION  = 'Description';

    /**
     * @var \Diamante\DeskBundle\Model\User\UserDetailsService
     * @Mock Diamante\DeskBundle\Model\User\UserDetailsService
     */
    private $userDetailsService;

    /**
     * @var TicketApiServiceImpl
     */
    private $ticketService;

    /**
     * @var \Diamante\DeskBundle\Model\Ticket\TicketRepository
     * @Mock \Diamante\DeskBundle\Model\Ticket\TicketRepository
     */
    private $ticketRepository;

    /**
     * @var \Diamante\DeskBundle\Model\Attachment\Manager
     * @Mock \Diamante\DeskBundle\Model\Attachment\Manager
     */
    private $attachmentManager;

    /**
     * @var \Diamante\DeskBundle\Entity\Ticket
     * @Mock \Diamante\DeskBundle\Entity\Ticket
     */
    private $ticket;

    /**
     * @var \Diamante\DeskBundle\Model\Shared\Repository
     * @Mock \Diamante\DeskBundle\Model\Shared\Repository
     */
    private $branchRepository;

    /**
     * @var \Diamante\DeskBundle\Model\Ticket\TicketBuilder
     * @Mock \Diamante\DeskBundle\Model\Ticket\TicketBuilder
     */
    private $ticketBuilder;

    /**
     * @var \Diamante\DeskBundle\Model\Shared\UserService
     * @Mock \Diamante\DeskBundle\Model\Shared\UserService
     */
    private $userService;

    /**
     * @var \Diamante\DeskBundle\Model\Shared\Authorization\AuthorizationService
     * @Mock \Diamante\DeskBundle\Model\Shared\Authorization\AuthorizationService
     */
    private $authorizationService;

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcher
     * @Mock \Symfony\Component\EventDispatcher\EventDispatcher
     */
    private $dispatcher;

    /**
     * @var NotificationDeliveryManager
     */
    private $notificationDeliveryManager;

    /**
     * @var \Diamante\DeskBundle\Model\Ticket\Notifications\Notifier
     * @Mock \Diamante\DeskBundle\Model\Ticket\Notifications\Notifier
     */
    private $notifier;

    /**
     * @var \Diamante\DeskBundle\Api\ApiPagingService
     * @Mock Diamante\DeskBundle\Api\ApiPagingService
     */
    private $apiPagingService;

    protected function setUp()
    {
        MockAnnotations::init($this);

        $this->notificationDeliveryManager = new NotificationDeliveryManager();

        $this->ticketService = new TicketApiServiceImpl(
            $this->ticketRepository,
            $this->branchRepository,
            $this->ticketBuilder,
            $this->attachmentManager,
            $this->userService,
            $this->authorizationService,
            $this->dispatcher,
            $this->notificationDeliveryManager,
            $this->notifier
        );

        $this->ticketService->setApiPagingService($this->apiPagingService);
        $this->ticketService->setUserDetailsService($this->userDetailsService);
    }

    /**
     * @test
     */
    public function testTicketsAreFiltered()
    {
        $tickets = array(
            new Ticket(
                new UniqueId('unique_id'),
                new TicketSequenceNumber(13),
                self::SUBJECT,
                self::DESCRIPTION,
                $this->createBranch(),
                new User(1, User::TYPE_DIAMANTE),
                $this->createAssignee(),
                new Source(Source::PHONE),
                new Priority(Priority::PRIORITY_LOW),
                new Status(Status::CLOSED)
            ),
            new Ticket(
                new UniqueId('unique_id'),
                new TicketSequenceNumber(12),
                self::SUBJECT,
                self::DESCRIPTION,
                $this->createBranch(),
                new User(1, User::TYPE_ORO),
                $this->createAssignee(),
                new Source(Source::PHONE),
                new Priority(Priority::PRIORITY_LOW),
                new Status(Status::CLOSED)
            ),
        );

        $command = new FilterTicketsCommand();
        $command->reporter = 'oro_1';
        $pagingInfo = new PagingInfo(1, new FilterPagingProperties());

        $this->ticketRepository
            ->expects($this->once())
            ->method('filter')
            ->with($this->equalTo(array(array('reporter','eq','oro_1'))), $this->equalTo(new FilterPagingProperties()))
            ->will($this->returnValue(array($tickets[1])));

        $this->apiPagingService
            ->expects($this->once())
            ->method('getPagingInfo')
            ->will($this->returnValue($pagingInfo));

        $this->userDetailsService
            ->expects($this->atLeastOnce())
            ->method('fetch')
            ->with($this->createDiamanteUser())
            ->will($this->returnValue($this->createUserDetails()));


        $retrievedTickets = $this->ticketService->listAllTickets($command);

        $this->assertNotNull($retrievedTickets);
        $this->assertTrue(is_array($retrievedTickets));
        $this->assertNotEmpty($retrievedTickets);
        $this->assertEquals($tickets[1], $retrievedTickets[0]);
    }

    /**
     * @return Branch
     */
    private function createBranch()
    {
        return new Branch('DUMM', 'DUMMY_NAME', 'DUMYY_DESC');
    }

    /**
     * @return OroUser
     */
    private function createAssignee()
    {
        return $this->createOroUser();
    }

    /**
     * @return OroUser
     */
    private function createOroUser()
    {
        return new OroUser();
    }

    private function createDiamanteUser()
    {
        return new User(1, User::TYPE_ORO);
    }

    private function createUserDetails()
    {
        return new UserDetails(1,User::TYPE_ORO,'dummy@email.com','First','Last','dummy');
    }
}
