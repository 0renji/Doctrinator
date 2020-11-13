<?php
// /home/orenji/Desktop/projectTestFolder
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
/**
* @ORM\Entity
* @ORM\Table(name="addresses")
*/
class AddressInstance { 
	/**
	 * @ORM\Id
	 * @ORM\GeneratedValue
	 * @ORM\Column(type="integer",nullable=true)
	 * @var int id
	 */
	protected $id;

	/**
	 * @ORM\Column(type="string",nullable=true)
	 * @var string company_label
	 */
	protected $company_label;

	/**
	 * @ORM\Column(type="string",nullable=true)
	 * @var string company_additional
	 */
	protected $company_additional;

	/**
	 * @ORM\Column(type="string",nullable=true)
	 * @var string recipient_additional
	 */
	protected $recipient_additional;

	/**
	 * @ORM\Column(type="string",nullable=true)
	 * @var string street
	 */
	protected $street;

	/**
	 * @ORM\Column(type="string",nullable=true)
	 * @var string street_additional
	 */
	protected $street_additional;

	/**
	 * @ORM\Column(type="string",nullable=true)
	 * @var string postcode
	 */
	protected $postcode;

	/**
	 * @ORM\Column(type="string",nullable=true)
	 * @var string city
	 */
	protected $city;

	/**
	 * @ORM\Column(type="float",nullable=true)
	 * @var string lat
	 */
	protected $lat;

	/**
	 * @ORM\Column(type="float",nullable=true)
	 * @var float lng
	 */
	protected $lng;

	/**
	 * @ORM\Column(type="string",nullable=true)
	 * @var float region
	 */
	protected $region;

	/**
	 * @ORM\Column(type="string",nullable=true)
	 * @var string district
	 */
	protected $district;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return AddressInstance
     */
    public function setId(int $id): AddressInstance
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getCompanyLabel(): string
    {
        return $this->company_label;
    }

    /**
     * @param string $company_label
     * @return AddressInstance
     */
    public function setCompanyLabel(string $company_label): AddressInstance
    {
        $this->company_label = $company_label;
        return $this;
    }

    /**
     * @return string
     */
    public function getCompanyAdditional(): string
    {
        return $this->company_additional;
    }

    /**
     * @param string $company_additional
     * @return AddressInstance
     */
    public function setCompanyAdditional(string $company_additional): AddressInstance
    {
        $this->company_additional = $company_additional;
        return $this;
    }

    /**
     * @return string
     */
    public function getRecipientAdditional(): string
    {
        return $this->recipient_additional;
    }

    /**
     * @param string $recipient_additional
     * @return AddressInstance
     */
    public function setRecipientAdditional(string $recipient_additional): AddressInstance
    {
        $this->recipient_additional = $recipient_additional;
        return $this;
    }

    /**
     * @return string
     */
    public function getStreet(): string
    {
        return $this->street;
    }

    /**
     * @param string $street
     * @return AddressInstance
     */
    public function setStreet(string $street): AddressInstance
    {
        $this->street = $street;
        return $this;
    }

    /**
     * @return string
     */
    public function getStreetAdditional(): string
    {
        return $this->street_additional;
    }

    /**
     * @param string $street_additional
     * @return AddressInstance
     */
    public function setStreetAdditional(string $street_additional): AddressInstance
    {
        $this->street_additional = $street_additional;
        return $this;
    }

    /**
     * @return string
     */
    public function getPostcode(): string
    {
        return $this->postcode;
    }

    /**
     * @param string $postcode
     * @return AddressInstance
     */
    public function setPostcode(string $postcode): AddressInstance
    {
        $this->postcode = $postcode;
        return $this;
    }

    /**
     * @return string
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @param string $city
     * @return AddressInstance
     */
    public function setCity(string $city): AddressInstance
    {
        $this->city = $city;
        return $this;
    }

    /**
     * @return string
     */
    public function getLat(): string
    {
        return $this->lat;
    }

    /**
     * @param string $lat
     * @return AddressInstance
     */
    public function setLat(string $lat): AddressInstance
    {
        $this->lat = $lat;
        return $this;
    }

    /**
     * @return float
     */
    public function getLng(): float
    {
        return $this->lng;
    }

    /**
     * @param float $lng
     * @return AddressInstance
     */
    public function setLng(float $lng): AddressInstance
    {
        $this->lng = $lng;
        return $this;
    }

    /**
     * @return float
     */
    public function getRegion(): float
    {
        return $this->region;
    }

    /**
     * @param float $region
     * @return AddressInstance
     */
    public function setRegion(float $region): AddressInstance
    {
        $this->region = $region;
        return $this;
    }

    /**
     * @return string
     */
    public function getDistrict(): string
    {
        return $this->district;
    }

    /**
     * @param string $district
     * @return AddressInstance
     */
    public function setDistrict(string $district): AddressInstance
    {
        $this->district = $district;
        return $this;
    }


// Method __construct has been removed, if it's needed please implement it by hand.

// Method toHuman has been removed, if it's needed please implement it by hand.

// Method checkValid has been removed, if it's needed please implement it by hand.

// Method _isValid has been removed, if it's needed please implement it by hand.

// Method addGeodata has been removed, if it's needed please implement it by hand.

// Method setFirstAddressLine has been removed, if it's needed please implement it by hand.

// Method isTemporary has been removed, if it's needed please implement it by hand.

// Method getRegion has been removed, if it's needed please implement it by hand.

// Method getTimezone has been removed, if it's needed please implement it by hand.

}
