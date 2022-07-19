<?php

namespace TokenAuth;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use TokenAuth\Contracts\AuthTokenContract;

class NewAuthToken implements Arrayable, Jsonable {
    /**
     * @var \TokenAuth\Contracts\AuthTokenContract|\Illuminate\Database\Eloquent\Model The token instance.
     */
    public $token;

    /**
     * @var string The plain text version of the token.
     */
    public $plainTextToken;

    /**
     * Create a new access token result.
     *
     * @param \TokenAuth\Contracts\AuthTokenContract|\Illuminate\Database\Eloquent\Model $token
     * @param string $plainTextToken
     * @return void
     */
    public function __construct(
        AuthTokenContract $token,
        string $plainTextToken
    ) {
        $this->token = $token;
        $this->plainTextToken = $plainTextToken;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray() {
        return [
            'token' => $this->token,
            'plainTextToken' => $this->plainTextToken,
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0) {
        return json_encode($this->toArray(), $options);
    }
}
