Feature: Revisions

  Scenario: Generate, list, remove revisions
    Given a WP install

    When I run `wp revisions list`
    Then STDOUT should contain:
      """
      Success: 0 revisions.
      """

    When I run `wp revisions generate`
    And I run `wp revisions list`
    Then STDOUT should contain:
      """
      Success: 30 revisions.
      """

    When I run `wp revisions generate 12 --post_id=1`
    And I run `wp revisions list`
    Then STDOUT should contain:
      """
      Success: 42 revisions.
      """

    When I run `wp revisions clean 5`
    Then STDOUT should contain:
      """
      Success: Finished removing 32 old revisions.
      """

    When I run `wp revisions list`
    Then STDOUT should contain:
      """
      Success: 10 revisions.
      """

    When I run `wp revisions dump --yes`
    Then STDOUT should contain:
      """
      Success: Finished removing all revisions.
      """

    When I run `wp revisions list`
    Then STDOUT should contain:
      """
      Success: 0 revisions.
      """

    When I run `wp post generate --count=10 --post_date=2000-10-10`
    And I run `wp post generate --count=10`
    And I run `wp revisions generate 2 --post_type=post`
    And I run `wp revisions list`
    Then STDOUT should contain:
      """
      Success: 42 revisions.
      """

    When I run `wp revisions clean -1 --after-date=2010-10-10`
    Then STDOUT should contain:
      """
      Success: Finished removing 22 old revisions.
      """

