<?php

class Domain{

    private $idna;
    private $name;
    private $whois = null;

    public function __construct( $name ){
        $this->idna = new idna_convert();

        $this->setName($this->idna->decode($name));
    }

    public function getPunycode(){
        return $this->idna->encode( $this->getName() );
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Return ASC domain TLD
     *
     * @return mixed
     */
    public function getTLD(){

        preg_match("/^(https?:\/\/)?(\.?www\.|\.)?[^\.]+\.(.*)$/", $this->getName(), $zone);
        $zone = mb_strtolower(trim($zone[3]),"UTF-8");

        return $zone;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function getWhois(){
        return $this->whois;
    }

    public function setWhois( $whois ){
        $this->whois = $whois;
    }
}