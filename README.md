envmon
======

JSON HTTP API/storage backend for weather/environmental monitoring.

* Coded in PHP.
* MongoDB database backend.
* Single dependency beyond stock PHP: MongoDB driver (PECL mongo.)
* All data for each day stored in a single document, with daily aggregate values calculated as data comes in.

## Messages ##

### POST ###

Data is sent to the API for handling by POSTing a JSON document.

The database document for a day is divided into five-minute timeslots, which are numbered 0 through 287, with timeslot 0 being the period from 00:00:00 local time through 00:04:59. The message format is defined thus:

* device_id (see note on sensors, below.)
* date - record date, in YYYY-MM-DD format (from ISO8601.)
* timeslot - either 0 to 287, or the start time of the timeslot in hh:mm format. If the latter format is used, values not corresponding to the start of a five-minute timeslot will be rejected.
* data - arbitrary array of data. 
* replace - optional; if data for a specific date/timeslot/sensor is re-sent, a 409 Conflict error will be returned unless the replace parameter is passed as true.

An example of temperature data may contain the maximum, minimum, and mean temperature for the five-minute timeslot, along with a count of the number of times the sensor was sampled. When temperature data is aggregated, the MEAN temperature from the timeslot will be used. Data would thus look like this:

```
data : {
  "max" : 21.4,
  "min" : 20.8,
  "mean" : 21.0,
  "count" : 20
}
```

A rain gauge may send the count for the timeslot, along with the absolute count. (Absolute count: the number of events detected by the counter since it was reset. Count for timeslot is calculated by subtracting the absolute count from the previous timeslot from the absolute count of the current timeslot.)

```
data : {
  "count" : 4,
  "absolute_count" : 879
}
```

An earth field magnetometer would have fields similar to that for a temperature sensor, by threefold, for the X, Y, Z axes.

### GET ###

Data for individual days may retrieved by passing the following parameters in the query string:

* date ( YYYY-MM-DD )
* timeslot ( 0 - 287, or 00:00 - 23:55, in five-minute increments. )
* device_id ( assumed to be a hexadecimal string, UUID, or similar. )

If only the date is passed, the entire document for the day will be returned. If date and timeslot are passed, all data (all sensors) will be returned for the given timeslot. If date, timeslot, and device_id are all passed, only the sub-document for given sensor and timeslot is returned.

Note that date/timeslot/device_id could be used for calculating rainfall values, if the previous absolute count is not stored locally.

The combination of timeslot, device_id (not date) is not supported.

To retrieve data for multiple days, the following parameters are used:

* date_from (YYYY-MM-DD)
* date_to (YYYY-MM-DD)

In the event that no data is available for a GET request, the API will return error 404 Not Found.


## Configuration ##

Configuration for the system is through a JSON document, siteconfig.json, which is located in the server directory. The various sections of this document are described below.

### auth ###

* useauth (boolean, default false) - use authorisation.
* user (string) - user name for basic authentication
* pass (string) - password for basic authentication

### database ###

MongoDB connection parameters. Note that this does not include any advanced parameters, such as sharing, or replication.

* useauth (boolean, default false) - whether or not to provide authentication information with connection.
* user (string or null) - MongoDB user
* pass (string or null) - MongoDB password
* db (string, default 'envmon') - database name
* host (string, default 'localhost') - database host
* port (integer, default 27017) - database port

### geo ###

Geospatial data, recorded in every day's document, used to calculate sunrise/sunset times, and any other astronomical data that may be displayed.

* show (boolean) - whether or not to show location on public pages. Note that this parameter does not supress the output of the geo array, it merely acts as an indicator to any downstream software that this information should be redacted before presentation.
* lat (float, or null) - latitude.
* long (float, or null) - longitude.
* alt (integer, or null) - elevation.
* utc_offset (float) - local timezone offset from UTC (hours.) Note that system ignores daylight savings.

### sensors ###

List of all sensors on which system is reporting.

* device_id (string) - unique identifier for device. If using 1-Wire devices, this would be the part serial number. In the absence of the device having an ID, I would suggest generating a UUID for this purpose.
* type (string) - temperature, rain (list will be expanded.)
* title (string) - device title, for reports.
* location (string) - location of device, for reports.
* units (string) - units of measurement, for reports.
* ag_method (string) - aggregation method, determines how data is aggregated.

### ag_methods ###

Templates used when a day's document is created. Field names used are thus:

* max - maximum value
* min - minimum value
* mean - mean value
* sd - standard deviation
* max_ts - timeslot at which maximum value was measured (or was first measured.)
* min_ts - timeslot at which minimum value was measured.
* count - daily total count, for the likes of precipitation.

## Code ##

Most operations are based around that ENVMON class, which is defined in server/envmon.php.  The API code is found in htdocs/index.php.

## Testing ##

An HTML/JavaScript test harness for sending data to the API is located in the client directory.

## Status ##

### Implemented ###

* POST - saving records, but without aggregation.
* GET - single records.
* GET - date range.

### To Do ###

* Authentication disabled for testing, REINSTATE.
* Sunrise/sunset.
* Moon phase.
* Batch mode - pass multiple documents in a single request.



