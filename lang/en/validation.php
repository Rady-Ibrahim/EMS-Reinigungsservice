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

    'attributes' => [
        'email'    => 'Email',
        'password' => 'Password',
        'name'     => 'Name',
        'role'     => 'Role',
        'locale'   => 'Language',
    ],
];
