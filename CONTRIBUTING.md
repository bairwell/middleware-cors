Contributing
-------------

## Pull Requests

1. Fork the repository
2. Create a new branch for each feature or improvement
3. Send a pull request from each feature branch to the **develop** branch

It is very important to separate new features or improvements into separate feature branches, and to send a
pull request for each branch. This allows me to review and pull in new features or improvements individually.

## Style Guide

All pull requests must adhere to the [PSR-2 standard](http://www.php-fig.org/psr/psr-2/).

This can be checked via, you can run the following commands to check if everything is ready to submit:

    cd cors
    vendor/bin/phpcs -np

Which should give you no output, indicating that there are no coding standard errors. And then:


## Unit Testing

All pull requests must be accompanied by passing unit tests and complete code coverage. The Bairwell\Cors library uses phpunit for testing.

[Learn about PHPUnit](https://github.com/sebastianbergmann/phpunit/)

    cd cors
    vendor/bin/phpunit

Which should give you no failures or errors. You can ignore any skipped tests as these are for external tools.
