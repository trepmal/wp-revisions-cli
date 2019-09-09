Feature: Revisions

  Scenario: Generate, list, remove revisions
    Given a WP install

    When I run `wp revisions list`
    Then the return code should be 0

    When I run `wp revisions generate`
    And I run `wp revisions list --format=count`
    Then STDOUT should contain:
      """
      45
      """

    When I run `wp revisions generate 12 --post_id=1`
    And I run `wp revisions list --format=count`
    Then STDOUT should contain:
      """
      57
      """

    When I run `wp revisions clean 5`
    Then STDOUT should contain:
      """
      Success: Finished removing 42 old revisions.
      """

    When I run `wp revisions list --format=count`
    Then STDOUT should contain:
      """
      15
      """

    When I run `wp revisions dump --yes`
    Then STDOUT should contain:
      """
      Success: Finished removing all revisions.
      """

    When I run `wp revisions list --format=count`
    Then STDOUT should contain:
      """
      0
      """

    When I run `wp post generate --count=10 --post_date=2000-10-10`
    And I run `wp post generate --count=10`
    And I run `wp revisions generate 2 --post_type=post`
    And I run `wp revisions list --format=count`
    Then STDOUT should contain:
      """
      42
      """

    When I run `wp revisions clean -1 --after-date=2010-10-10`
    Then STDOUT should contain:
      """
      Success: Finished removing 22 old revisions.
      """

