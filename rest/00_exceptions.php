<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

class RestException extends RuntimeException
{
    public function __construct($code, $message = null)
    {
        parent::__construct($message, $code);
    }
    public function getData()
    {
        return null;
    }
}

class UserInputException extends RestException
{ // use this exception to return invalid user input
    private $internalCode = 0;
    public function __construct($code, $message = null, $internalCode = null)
    {
        parent::__construct($code, $message);
        $this->internalCode = $internalCode;
    }
    public function getData()
    {
        return array("internal-code"=>$this->internalCode);
    }
}

class FieldValidationException extends UserInputException
{ // use this exception to return invalidity of submitted fields
    private $invalidFields = 0;
    public function __construct($message = null, $invalidFields = array(), $internalCode = null)
    {
        parent::__construct(422, $message, $internalCode);
        $this->invalidFields = $invalidFields;
    }
    public function getData()
    {
        $array = parent::getData();
        $array["fields"] = $this->invalidFields;
        return $array;
    }
}

class MissingFieldsException extends UserInputException
{ // use this exception to return missing fields on submission
    private $missingFields = 0;
    public function __construct($message = null, $missingFields = array(), $internalCode = null)
    {
        parent::__construct(400, $message, $internalCode);
        $this->missingFields = $missingFields;
    }
    public function getData()
    {
        $array = parent::getData();
        $array["missing-fields"] = $this->missingFields;
        return $array;
    }
}
