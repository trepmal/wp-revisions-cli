Feature: Revisions

  Scenario: Clean revisions
    Given a WP install

    When I run `wp post generate --count=1 --post_date="2018-07-01"`
    And I run `wp revisions generate`
    And I run `wp revisions list --format=count`
    Then STDOUT should contain:
      """
      60
      """

    When I run `wp revisions clean 5 --before-date=2018-07-01 --dry-run`
    Then STDOUT should contain:
      """
      Success: Dry Run: Will remove 0 old revisions.
      """

    When I run `wp revisions clean 5 --before-date=2018-07-02`
    Then STDOUT should contain:
      """
      Success: Finished removing 10 old revisions.
      """

    When I run `wp revisions clean 5 --post_type=page`
    Then STDOUT should contain:
      """
      Success: Finished removing 20 old revisions.
      """

    When I run `wp revisions clean 5`
    Then STDOUT should contain:
      """
      Success: Finished removing 10 old revisions.
      """
