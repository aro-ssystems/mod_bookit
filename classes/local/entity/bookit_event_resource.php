<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Event-resource relationship entity.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\entity;

/**
 * Event-resource relationship entity.
 *
 * Pure data entity for tracking which resources are used in which events.
 * No business logic or persistence methods.
 */
class bookit_event_resource {
    /** @var int|null Record ID */
    private ?int $id;

    /** @var int Event ID */
    private int $eventid;

    /** @var int Resource ID */
    private int $resourceid;

    /** @var int Quantity of resource units used */
    private int $quantity;

    /** @var string Status (requested, confirmed, inprogress, rejected) */
    private string $status;

    /** @var int User who created/modified */
    private int $usermodified;

    /** @var int Creation timestamp */
    private int $timecreated;

    /** @var int Modification timestamp */
    private int $timemodified;

    /**
     * Constructor.
     *
     * @param int|null $id Record ID
     * @param int $eventid Event ID
     * @param int $resourceid Resource ID
     * @param int $quantity Quantity
     * @param string $status Status
     * @param int $usermodified User ID
     * @param int $timecreated Creation timestamp
     * @param int $timemodified Modification timestamp
     */
    public function __construct(
        ?int $id = null,
        int $eventid = 0,
        int $resourceid = 0,
        int $quantity = 1,
        string $status = 'requested',
        int $usermodified = 0,
        int $timecreated = 0,
        int $timemodified = 0
    ) {
        $this->id = $id;
        $this->eventid = $eventid;
        $this->resourceid = $resourceid;
        $this->quantity = $quantity;
        $this->status = $status;
        $this->usermodified = $usermodified;
        $this->timecreated = $timecreated;
        $this->timemodified = $timemodified;
    }

    /**
     * Get record ID.
     *
     * @return int|null
     */
    public function get_id(): ?int {
        return $this->id;
    }

    /**
     * Set record ID.
     *
     * @param int|null $id
     */
    public function set_id(?int $id): void {
        $this->id = $id;
    }

    /**
     * Get event ID.
     *
     * @return int
     */
    public function get_eventid(): int {
        return $this->eventid;
    }

    /**
     * Set event ID.
     *
     * @param int $eventid
     */
    public function set_eventid(int $eventid): void {
        $this->eventid = $eventid;
    }

    /**
     * Get resource ID.
     *
     * @return int
     */
    public function get_resourceid(): int {
        return $this->resourceid;
    }

    /**
     * Set resource ID.
     *
     * @param int $resourceid
     */
    public function set_resourceid(int $resourceid): void {
        $this->resourceid = $resourceid;
    }

    /**
     * Get quantity.
     *
     * @return int
     */
    public function get_quantity(): int {
        return $this->quantity;
    }

    /**
     * Set quantity.
     *
     * @param int $quantity
     */
    public function set_quantity(int $quantity): void {
        $this->quantity = $quantity;
    }

    /**
     * Get status.
     *
     * @return string
     */
    public function get_status(): string {
        return $this->status;
    }

    /**
     * Set status.
     *
     * @param string $status
     */
    public function set_status(string $status): void {
        $this->status = $status;
    }

    /**
     * Get usermodified.
     *
     * @return int
     */
    public function get_usermodified(): int {
        return $this->usermodified;
    }

    /**
     * Set usermodified.
     *
     * @param int $usermodified
     */
    public function set_usermodified(int $usermodified): void {
        $this->usermodified = $usermodified;
    }

    /**
     * Get timecreated.
     *
     * @return int
     */
    public function get_timecreated(): int {
        return $this->timecreated;
    }

    /**
     * Set timecreated.
     *
     * @param int $timecreated
     */
    public function set_timecreated(int $timecreated): void {
        $this->timecreated = $timecreated;
    }

    /**
     * Get timemodified.
     *
     * @return int
     */
    public function get_timemodified(): int {
        return $this->timemodified;
    }

    /**
     * Set timemodified.
     *
     * @param int $timemodified
     */
    public function set_timemodified(int $timemodified): void {
        $this->timemodified = $timemodified;
    }
}
