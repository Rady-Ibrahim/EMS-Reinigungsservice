<?php

return [
    'required'  => 'Das Feld :attribute ist erforderlich.',
    'email'     => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
    'min'       => [
        'string' => 'Das Feld :attribute muss mindestens :min Zeichen lang sein.',
    ],
    'max'       => [
        'string' => 'Das Feld :attribute darf maximal :max Zeichen lang sein.',
    ],
    'in'        => 'Der ausgewählte Wert für :attribute ist ungültig.',
    'unique'    => 'Das Feld :attribute ist bereits vergeben.',
    'confirmed' => 'Die Bestätigung des Feldes :attribute stimmt nicht überein.',

    'current_password_incorrect' => 'Das aktuelle Passwort ist nicht korrekt.',
    'resolution_required'         => 'Eine Beschreibung der Lösung ist erforderlich.',

    'attributes' => [
        'email'            => 'E-Mail',
        'password'         => 'Passwort',
        'current_password' => 'Aktuelles Passwort',
        'resolution'       => 'Lösung',
        'name'             => 'Name',
        'role'             => 'Rolle',
        'locale'           => 'Sprache',
    ],
];
