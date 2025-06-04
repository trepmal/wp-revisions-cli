trepmal/wp-revisions-cli
========================

Manage revisions

[![Testing](https://github.com/trepmal/wp-revisions-cli/actions/workflows/testing.yml/badge.svg)](https://github.com/trepmal/wp-revisions-cli/actions/workflows/testing.yml)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using

This package implements the following commands:

### wp revisions list

List all revisions

~~~
wp revisions list [--post_type=<post-type>] [--post_id=<post-id>] [--fields=<fields>] [--yes] [--format=<format>]
~~~

**OPTIONS**

	[--post_type=<post-type>]
		List revisions for given post type(s).

	[--post_id=<post-id>]
		List revisions for given post. Trumps --post_type.

	[--fields=<fields>]
		Comma-separated list of fields to be included in the output.
		---
		default: ID,post_title,post_parent
		---

	[--yes]
		Answer yes to the confirmation message.

	[--format=<format>]
		Format to use for the output. One of table, csv or json.

**EXAMPLES**

    wp revisions list
    wp revisions list --post_id=2
    wp revisions list --post_type=post,page



### wp revisions dump

Delete all revisions

~~~
wp revisions dump [--hard] [--yes]
~~~

**OPTIONS**

	[--hard]
		Hard delete. Slower, uses wp_delete_post_revision(). Alias to wp revisions clean -1

	[--yes]
		Answer yes to the confirmation message.

**EXAMPLES**

    wp revisions dump



### wp revisions clean

Delete old revisions

~~~
wp revisions clean [<keep>] [--post_type=<post-type>] [--after-date=<yyyy-mm-dd>] [--before-date=<yyyy-mm-dd>] [--post_id=<post-id>] [--hard] [--dry-run]
~~~

**OPTIONS**

	[<keep>]
		Number of revisions to keep per post. Defaults to WP_POST_REVISIONS if it is an integer

	[--post_type=<post-type>]
		Clean revisions for given post type(s). Default: any

	[--after-date=<yyyy-mm-dd>]
		Clean revisions on posts published on or after this date. Default: none.

	[--before-date=<yyyy-mm-dd>]
		Clean revisions on posts published on or before this date. Default: none.

	[--post_id=<post-id>]
		Clean revisions for given post.

	[--hard]
		Hard delete. Slower, uses wp_delete_post_revision().

	[--dry-run]
		Dry run, just a test, no actual cleaning done.

**EXAMPLES**

    wp revisions clean
    wp revisions clean 5
    wp revisions clean --post_id=2
    wp revisions clean 5 --post_type=post,page
    wp revisions clean --after-date=2015-11-01 --before-date=2015-12-30
    wp revisions clean --after-date=2015-11-01 --before-date=2015-12-30 --dry-run



### wp revisions generate

Generate revisions

~~~
wp revisions generate [<count>] [--post_type=<post-type>] [--post_id=<post-id>]
~~~

**OPTIONS**

	[<count>]
		Number of revisions to generate per post. Default 15

	[--post_type=<post-type>]
		Generate revisions for given post type(s). Default any

	[--post_id=<post-id>]
		Generate revisions for given post.

**EXAMPLES**

    wp revisions generate 10
    wp revisions generate --post_id=2
    wp revisions generate 2 --post_type=post,page



### wp revisions status

Get revision status

~~~
wp revisions status 
~~~

**OPTIONS**

**EXAMPLES**

    wp revisions status

## Installing

Installing this package requires WP-CLI v2.1 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with:

    wp package install git@github.com:trepmal/wp-revisions-cli.git

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/trepmal/wp-revisions-cli/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/trepmal/wp-revisions-cli/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/trepmal/wp-revisions-cli/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

## Support

Github issues aren't for general support questions, but there are other venues you can try: https://wp-cli.org/#support


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
