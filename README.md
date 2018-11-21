# PHP API Core - Agnostic Class for Resful APIs

### Description

This PHP class is designed to provide a single library for all RESTful API integrations, it prevents the need to download and use individual libraries for multiple services used in a project. The core class uses a combination of php Magic methods to construct api calls based on chaining, meaning that the class itself needs to have no knowledge of API structure and can be used with any integration that provides a RESTful series of endpoints.

### Installation

If you can, this should be installed with composer. Please note this is not currently available through packagist, so you will need to add the following repository to your composer.json:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https:\/\/codestore.sc.vg"
        }
    ]
}
```
```bash
$ composer require n1ghteyes/apicore
```

### Basic usage
