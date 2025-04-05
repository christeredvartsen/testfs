# Contributing to christeredvartsen/testfs

If you want to contribute to christeredvartsen/testfs please use the following guidelines.

## Running tests and static analysis

Before pushing code, make sure tests and static analysis passes. Use the following composer scripts to quickly run the different tests:

### Unit tests

    composer run test

### Static analysis

    composer run sa

## Reporting bugs / feature requests

Use the [issue tracker on GitHub](https://github.com/christeredvartsen/testfs/issues) for this. When reporting a bug please add the necessary steps that can reproduce the bug.

## Submitting a pull request

If you want to implement a new feature, fork this project and create a feature branch called `feature/my-awesome-feature`, and send a pull request. The feature needs to be fully documented and tested before it will be merged. Pull requests with a failing build will be ignored until the build passes.

If the pull request is a bug fix, remember to file an issue in the issue tracker first, then create a branch called `issue/<issue number>`. One or more test cases to verify the bug is required. When creating specific test cases for issues/bugs, please add a `@see` tag to the docblock, for instance:

```php
/**
 * @see https://github.com/christeredvartsen/testfs/issues/{issue-number}
 */
public function testSomething
    // ...
}
```

Please also specify which commit that resolves the bug by adding `Resolves #<issue number>` to the commit message.

## Coding standards

Simply use the same coding standard already found in the PHP files in the project.
