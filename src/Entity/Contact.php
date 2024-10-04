<?php

namespace App\Entity;

class Contact
{
    private string $Nom;
    private string $Email;
    private string $Sujet;
    private string $Message;

    // Getters
    public function getNom(): string
    {
        return $this->Nom;
    }

    public function getEmail(): string
    {
        return $this->Email;
    }

    public function getSujet(): string
    {
        return $this->Sujet;
    }

    public function getMessage(): string
    {
        return $this->Message;
    }

    // Setters
    public function setNom(string $Nom): self
    {
        $this->Nom = $Nom;

        return $this;
    }

    public function setEmail(string $Email): self
    {
        $this->Email = $Email;

        return $this;
    }

    public function setSujet(string $Sujet): self
    {
        $this->Sujet = $Sujet;

        return $this;
    }

    public function setMessage(string $Message): self
    {
        $this->Message = $Message;

        return $this;
    }
}
