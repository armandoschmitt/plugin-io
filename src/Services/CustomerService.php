<?php //strict

namespace LayoutCore\Services;

use LayoutCore\Models\LocalizedOrder;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Contracts\ContactAddressRepositoryContract;
use Plenty\Modules\Account\Contact\Models\Contact;
use LayoutCore\Builder\Order\AddressType;
use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Plugin\Application;
use LayoutCore\Helper\AbstractFactory;
use LayoutCore\Helper\UserSession;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use LayoutCore\Services\AuthenticationService;

/**
 * Class CustomerService
 * @package LayoutCore\Services
 */
class CustomerService
{
	/**
	 * @var ContactRepositoryContract
	 */
	private $contactRepository;
	/**
	 * @var ContactAddressRepositoryContract
	 */
	private $addressRepository;
	/**
	 * @var OrderRepositoryContract
	 */
	private $orderRepository;
	/**
	 * @var AuthenticationService
	 */
	private $authService;
	/**
	 * @var UserSession
	 */
	private $userSession = null;
	/**
	 * @var AbstractFactory
	 */
	private $factory;

    /**
     * CustomerService constructor.
     * @param ContactRepositoryContract $contactRepository
     * @param ContactAddressRepositoryContract $addressRepository
     * @param OrderRepositoryContract $orderRepository
     * @param \LayoutCore\Services\AuthenticationService $authService
     * @param AbstractFactory $factory
     */
	public function __construct(
		ContactRepositoryContract $contactRepository,
		ContactAddressRepositoryContract $addressRepository,
		OrderRepositoryContract $orderRepository,
		AuthenticationService $authService,
		AbstractFactory $factory)
	{
		$this->contactRepository = $contactRepository;
		$this->addressRepository = $addressRepository;
		$this->orderRepository   = $orderRepository;
		$this->authService       = $authService;
		$this->factory           = $factory;
	}

    /**
     * Get the ID of the current contact from the session
     * @return int
     */
	public function getContactId():int
	{
		if($this->userSession === null)
		{
			$this->userSession = $this->factory->make(UserSession::class);
		}
		return $this->userSession->getCurrentContactId();
	}

    /**
     * Create a contact with addresses if specified
     * @param array $contactData
     * @param null $billingAddressData
     * @param null $deliveryAddressData
     * @return Contact
     */
	public function registerCustomer(array $contactData, $billingAddressData = null, $deliveryAddressData = null):Contact
	{
		$contact = $this->createContact($contactData);

		if($contact->id > 0)
		{
			//Login
			$this->authService->loginWithContactId($contact->id, (string)$contactData['password']);
		}

		if($billingAddressData !== null)
		{
			$this->createAddress($billingAddressData, AddressType::BILLING);
			if($deliveryAddressData === null)
			{
				$this->createAddress($billingAddressData, AddressType::DELIVERY);
			}
		}

		if($deliveryAddressData !== null)
		{
			$this->createAddress($deliveryAddressData, AddressType::DELIVERY);
		}

		return $contact;
	}

    /**
     * Create a new contact
     * @param array $contactData
     * @return Contact
     */
	public function createContact(array $contactData):Contact
	{
		$contact = $this->contactRepository->createContact($contactData);
		return $contact;
	}

    /**
     * Find the current contact by ID
     * @return null|Contact
     */
	public function getContact()
	{
		if($this->getContactId() > 0)
		{
			return $this->contactRepository->findContactById($this->getContactId());
		}
		return null;
	}

    /**
     * Update a contact
     * @param array $contactData
     * @return null|Contact
     */
	public function updateContact(array $contactData)
	{
		if($this->getContactId() > 0)
		{
			return $this->contactRepository->updateContact($contactData, $this->getContactId());
		}

		return null;
	}

    /**
     * List the addresses of a contact
     * @param null $type
     * @return array|\Illuminate\Database\Eloquent\Collection
     */
	public function getAddresses($type = null)
	{
		return $this->addressRepository->getAddresses($this->getContactId(), $type);
	}

    /**
     * Get an address by ID
     * @param int $addressId
     * @param int $type
     * @return Address
     */
	public function getAddress(int $addressId, int $type):Address
	{
		return $this->addressRepository->getAddress($addressId, $this->getContactId(), $type);
	}

    /**
     * Create an address with the specified address type
     * @param array $addressData
     * @param int $type
     * @return Address
     */
	public function createAddress(array $addressData, int $type):Address
	{
		$response = $this->addressRepository->createAddress($addressData, $this->getContactId(), $type);

		if($type == AddressType::BILLING)
		{
			$this->addressRepository->createAddress($addressData, $this->getContactId(), AddressType::DELIVERY);
		}
		elseif($type == AddressType::DELIVERY)
		{
			$this->addressRepository->createAddress($addressData, $this->getContactId(), AddressType::BILLING);
		}

		return $response;
	}

    /**
     * Update an address
     * @param int $addressId
     * @param array $addressData
     * @param int $type
     * @return Address
     */
	public function updateAddress(int $addressId, array $addressData, int $type):Address
	{
		return $this->addressRepository->updateAddress($addressData, $addressId, $this->getContactId(), $type);
	}

    /**
     * Delete an address
     * @param int $addressId
     * @param int $type
     */
	public function deleteAddress(int $addressId, int $type)
	{
		$this->addressRepository->deleteAddress($addressId, $this->getContactId(), $type);
	}

    /**
     * Get a list of orders for the current contact
     * @param int $page
     * @param int $items
     * @return array|\Plenty\Repositories\Models\PaginatedResult
     */
	public function getOrders(int $page = 1, int $items = 10)
	{
		return AbstractFactory::create(\LayoutCore\Services\OrderService::class)->getOrdersForContact(
		    $this->getContactId(),
            $page,
            $items
        );
	}

    /**
     * Get the last order created by the current contact
     * @return LocalizedOrder
     */
	public function getLatestOrder():LocalizedOrder
	{
        return AbstractFactory::create(\LayoutCore\Services\OrderService::class)->getLatestOrderForContact(
            $this->getContactId()
        );
	}
}
