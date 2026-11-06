@mod @mod_bookit @javascript
Feature: Create the exam course when a booking is confirmed
  In order to prepare the exam course without manual copying
  As a service-team user
  I need confirming a booking with a template to create exactly one hidden exam course.

  Background:
    Given the following "users" exist:
      | username    | firstname | lastname |
      | susiservice | Susi      | Service  |
      | bertbooking | Bert      | Booking  |
      | erikexam    | Erik      | Examiner |
    And the following "categories" exist:
      | name             | category | idnumber   |
      | Exam templates   | 0        | BOOKITTPL  |
      | Exam courses     | 0        | BOOKITEXAM |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "courses" exist:
      | fullname         | shortname | category  | visible |
      | Visible template | TPLVIS    | BOOKITTPL | 1       |
      | Hidden template  | TPLHID    | BOOKITTPL | 0       |
    And the following "roles" exist:
      | shortname   | name         | archetype |
      | serviceteam | Service-Team | student   |
      | bookingrole | Booking Role | student   |
    And the following "role capability" exists:
      | role                                  | serviceteam |
      | mod/bookit:view                       | allow       |
      | mod/bookit:viewownoverview            | allow       |
      | mod/bookit:managebasics               | allow       |
      | mod/bookit:viewalldetailsofevent      | allow       |
      | mod/bookit:editinternal               | allow       |
      | mod/bookit:editevent                  | allow       |
      | mod/bookit:selectexamcoursetemplate   | allow       |
      | mod/bookit:viewexamcourse             | allow       |
    And the following "role capability" exists:
      | role                                  | bookingrole |
      | mod/bookit:view                       | allow       |
      | mod/bookit:viewownoverview            | allow       |
      | mod/bookit:viewalldetailsofownevent   | allow       |
      | mod/bookit:selectexamcoursetemplate   | allow       |
    And the following "course enrolments" exist:
      | user        | course | role        |
      | susiservice | C1     | serviceteam |
      | bertbooking | C1     | bookingrole |
      | erikexam    | C1     | bookingrole |
    And the following "activities" exist:
      | activity | name               | course | idnumber |
      | bookit   | My BookIt Activity | C1     | 1        |
    And the Bookit exam course templates come from category "BOOKITTPL" and exam courses go to category "BOOKITEXAM"

  Scenario: Accepting from the request list creates the exam course
    Given the following "mod_bookit > events" exist:
      | name              | username    | personincharge_username | coursetemplate_shortname | startdate                            | enddate                              | bookingstatus | institution |
      | Chemistry exam    | bertbooking | erikexam                | TPLVIS                   | ##tomorrow 09:00##%Y-%m-%dT%H:%M:%S## | ##tomorrow 11:00##%Y-%m-%dT%H:%M:%S## | 1             | 1           |
    When I log in as "susiservice"
    And I open the Bookit overview "openrequests" for "My BookIt Activity"
    And I click the open request action "Accept" for event "Chemistry exam"
    And I run all adhoc tasks
    And I open the filtered Bookit overview "allrequests" for "My BookIt Activity" with status "-1" faculty "0" and semesters "current,next"
    And I open the Bookit event details for "Chemistry exam"
    Then I should see "Chemistry exam" in the "[data-name='refcourseid_readonly']" "css_element"

  Scenario: Saving the confirmed status in the booking form creates the exam course
    Given the following "mod_bookit > events" exist:
      | name              | username    | personincharge_username | coursetemplate_shortname | startdate                            | enddate                              | bookingstatus | institution |
      | Physics exam      | bertbooking | erikexam                | TPLVIS                   | ##next monday 12:00##%Y-%m-%dT%H:%M:%S## | ##next monday 14:00##%Y-%m-%dT%H:%M:%S## | 1             | 1           |
    When I log in as "susiservice"
    And I open the filtered Bookit overview "allrequests" for "My BookIt Activity" with status "-1" faculty "0" and semesters "current,next"
    And I open the Bookit event details for "Physics exam"
    And I select "Confirmed" in the Bookit event details control "bookingstatus"
    And I press "Save changes"
    And I run all adhoc tasks
    And I open the filtered Bookit overview "allrequests" for "My BookIt Activity" with status "-1" faculty "0" and semesters "current,next"
    And I open the Bookit event details for "Physics exam"
    Then I should see "Physics exam" in the "[data-name='refcourseid_readonly']" "css_element"

  Scenario: A booking person without enrolment can choose the visible template only
    Given the following "mod_bookit > events" exist:
      | name            | username    | personincharge_username | startdate                            | enddate                              | bookingstatus | institution |
      | Biology exam    | bertbooking | erikexam                | ##tomorrow 15:00##%Y-%m-%dT%H:%M:%S## | ##tomorrow 17:00##%Y-%m-%dT%H:%M:%S## | 0             | 1           |
    When I log in as "bertbooking"
    And I open the filtered Bookit overview "myevents" for "My BookIt Activity" with status "-1" faculty "0" and semesters "current,next"
    And I open the Bookit event details for "Biology exam"
    Then the Bookit event details control "coursetemplate" should contain option "Visible template"
    And the Bookit event details control "coursetemplate" should not contain option "Hidden template"

  Scenario Outline: On-site support sees the exam course only with the capability
    Given the following "users" exist:
      | username     | firstname | lastname |
      | sonjasupport | Sonja     | Support  |
    And the Bookit default roles are imported
    And the following "role capability" exists:
      | role                      | bookit_supportonsite |
      | mod/bookit:viewexamcourse | <permission>         |
    And the following "course enrolments" exist:
      | user         | course | role                 |
      | sonjasupport | C1     | bookit_supportonsite |
    And the following "mod_bookit > events" exist:
      | name           | username    | personincharge_username | supportperson_usernames | coursetemplate_shortname | startdate                            | enddate                              | bookingstatus | institution |
      | Geography exam | bertbooking | erikexam                | sonjasupport            | TPLVIS                   | ##tomorrow 09:00##%Y-%m-%dT%H:%M:%S## | ##tomorrow 11:00##%Y-%m-%dT%H:%M:%S## | 1             | 1           |
    And I log in as "susiservice"
    And I open the Bookit overview "openrequests" for "My BookIt Activity"
    And I click the open request action "Accept" for event "Geography exam"
    And I run all adhoc tasks
    And I log out
    When I log in as "sonjasupport"
    And I open the filtered Bookit overview "myevents" for "My BookIt Activity" with status "-1" faculty "0" and semesters "current,next"
    And I open the Bookit event details for "Geography exam"
    Then "[data-name='refcourseid_readonly']" "css_element" <visibility>

    Examples:
      | permission | visibility       |
      | allow      | should exist     |
      | prohibit   | should not exist |
