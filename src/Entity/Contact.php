<?php

namespace App\Entity;

class Contact {
    private $Nom;

    private $Email;

    private $Sujet; 

    private $Message;

    public function getNom()
    {
        return $this->Nom;
    }

    public function setNom(string $Nom)
    {
        $this->Nom = $Nom;

        return $this;
    }

    public function getEmail()
    {
        return $this->Email;
    }

    public function setEmail(string $Email)
    {
        $this->Email = $Email;

        return $this;
    }

    public function getSujet()
    {
        return $this->Sujet;
    }

    public function setSujet(string $Sujet)
    {
        $this->Sujet = $Sujet;

        return $this;
    }

    public function getMessage()
    {
        return $this->Message;
    }

    public function setMessage(string $Message)
    {
        $this->Message = $Message;

        return $this;
    }

}