
function process_form() {
  var req = new XMLHttpRequest();
  req.onreadystatechange = function() {
    if (req.readyState == 4) {
      console.log('Data received: ' + req.responseText);
      document.getElementById('response').innerText = req.responseText;
    }
  }

  req.setRequestHeader('Content-type', 'application/json');

  var authstring = 'Basic ' + window.btoa(unescape(encodeURICompoenent((
      document.getElementById('auth_user') + 
      ':' +
      document.getElementById('auth_password')
    ))));
  req.setRequestHeader('Authorization', authstring);

  var jdata;
  jdata.device_id = document.getElementById('device_id');
  jdata.type = document.getElementById('type');
  jdata.date = document.getElementById('date');
  jdata.timeslot = document.getElementById('timeslot');
  jdata.mean_value = document.getElementById('mean_value');
  jdata.min_value = document.getElementById('min_value');
  jdata.max_value = document.getElementById('max_value');

  json = JSON.stringify(jdata));
  console.log(json);
  req.send(json);

}

function set_defaults() {
}

document.addEventListener('DOMContentLoaded', function() {
  set_defaults();

  document.getElementById('send').addEventListener('click', process_form);
  document.getElementById('send').addEventListener('keypress', process_form);

  document.getElementById('response').innerText = 'Ready';
}
