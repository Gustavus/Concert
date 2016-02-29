<?php

namespace Gustavus\Concert\Setup\GeneratedEntities;

use Doctrine\ORM\Mapping as ORM;

/**
 * Locks
 *
 * @Table(name="locks", indexes={@Index(name="username", columns={"username"})})
 * @Entity
 */
class Locks
{
    /**
     * @var string
     *
     * @Column(name="filepathHash", type="string", length=32, nullable=false)
     * @Id
     * @GeneratedValue(strategy="IDENTITY")
     */
    private $filepathhash;

    /**
     * @var string
     *
     * @Column(name="filepath", type="string", length=2048, nullable=false)
     */
    private $filepath;

    /**
     * @var string
     *
     * @Column(name="username", type="string", length=32, nullable=false)
     */
    private $username;

    /**
     * @var \DateTime
     *
     * @Column(name="date", type="datetime", nullable=false)
     */
    private $date;


    /**
     * Get filepathhash
     *
     * @return string
     */
    public function getFilepathhash()
    {
        return $this->filepathhash;
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
