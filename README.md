envmon
======

JSON API/storage backend for weather/environmental monitoring.

* Coded in PHP.
* MongoDB database backend.
* Single dependency beyond stock PHP: MongoDB driver.
* All data for each day stored in a single document, with daily aggregates calculated at the end of the day.
