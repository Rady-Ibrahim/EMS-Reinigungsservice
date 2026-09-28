<?php

return [
    'required'  => 'The :attribute field is required.',
    'email'     => 'The :attribute field must be a valid email address.',
    'min'       => [
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'max'       => [
        'string' => 'The :attribute field must not exceed :max characters.',
    ],
    'in'        => 'The selected :attribute is invalid.',
    'unique'    => 'The :attribute has already been taken.',
    'confirmed' => 'The :attribute confirmation does not match.',

    'current_password_incorrect' => 'The current password is incorrect.',
    'resolution_required'         => 'A resolution note is required.',

    'attributes' => [
        'email'            => 'Email',
        'password'         => 'Password',
        'current_password' => 'Current password',
        'resolution'       => 'Resolution',
        'name'             => 'Name',
        'role'             => 'Role',
        'locale'           => 'Language',
    ],
];
