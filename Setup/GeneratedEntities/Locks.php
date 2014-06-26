<?php

namespace Gustavus\Concert\Setup\GeneratedEntities;

/**
 * Locks
 *
 * @Table(name="locks", indexes={@Index(name="username", columns={"username"})})
 * @Entity
 */
class Locks
{
    /**
     * @var integer
     *
     * @Column(name="filepathHash", type="string", length=32, nullable=false)
     * @Id
     * @GeneratedValue(strategy="IDENTITY")
     */
    private $filepathHash;

    /**
     * @var string
     *
     * @Column(name="filepath", type="string", length=2048, nullable=false)
     */
    private $filepath;

    /**
     * @var string
     *
     * @Column(name="username", type="string", length=24, nullable=false)
     */
    private $username;

    /**
     * @var \DateTime
     *
     * @Column(name="date", type="datetime", nullable=false)
     */
    private $date;


    /**
     * Get filepathHash
     *
     * @return integer
     */
    public function getId()
    {
        return $this->filepathHash;
    }

    /**
     * Set filepath
     *
     * @param string $filepath
     * @return Locks
     */
    public function setFilepath($filepath)
    {
        $this->filepath = $filepath;

        return $this;
    }

    /**
     * Get filepath
     *
     * @return string
     */
    public function getFilepath()
    {
        return $this->filepath;
    }

    /**
     * Set username
     *
     * @param string $username
     * @return Locks
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get username
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set date
     *
     * @param \DateTime $date
     * @return Locks
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date
     *
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }
}
