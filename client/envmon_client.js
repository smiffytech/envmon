
function process_form() {
  var req = new XMLHttpRequest();
  req.onreadystatechange = function() {
    if (req.readyState == 4) {
      console.log('Data received: ' + req.responseText);
      document.getElementById('response').innerText = req.responseText;
    }
  }

  req.open('POST', document.getElementById('uri').value);

  req.setRequestHeader('Content-type', 'application/json');

  var authstring = 'Basic ' + window.btoa(unescape(encodeURIComponent(
      document.getElementById('auth_user').value + 
      ':' +
      document.getElementById('auth_password').value
    )));
  req.setRequestHeader('Authorization', authstring);

  var jdata = {};
  jdata['device_id'] = document.getElementById('device_id').value;
  jdata['date'] = document.getElementById('date').value;
  jdata['timeslot'] = document.getElementById('timeslot').value;
  jdata['data'] = JSON.parse(document.getElementById('data').value);

  jdata['replace'] = ( document.getElementById('replace').selectedIndex == 1 ? true : false );

  var json = JSON.stringify(jdata);
  console.log(json);
  document.getElementById('txmit').innerText = json;
  req.send(json);

}

function timeslot() {
  var date = new Date;
  var t = Math.ceil( ( ( date.getHours() * 60 ) + date.getMinutes() ) / 12 );
  return(t);
}

function set_defaults() {
  var date = new Date;
  var day = date.getDate();
  var daystring = day;
  if (day < 10) {
    daystring = '0' + day;
  }

  var isodate = date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + daystring;

  document.getElementById('uri').value = 'http://envmon';
  document.getElementById('device_id').value = 'c00ffee';
  document.getElementById('date').value = isodate;
  document.getElementById('timeslot').value = timeslot();
  document.getElementById('auth_user').value = 'testuser';
  document.getElementById('auth_password').value = 'testpassword';

  var defdata = {};
  defdata.mean_value = 21.0;
  defdata.max_value = 21.9;
  defdata.min_value = 20.3;
  document.getElementById('data').value = JSON.stringify( defdata );
}


document.addEventListener('DOMContentLoaded', function() {
  set_defaults();

  document.getElementById('send').addEventListener('click', process_form);
  document.getElementById('send').addEventListener('keypress', process_form);

  document.getElementById('response').innerText = 'Ready';
});
