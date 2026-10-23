@props(['access'])
<button type="button" id="clock-office" class="btn btn-primary {{ $access['office'] ? '' : 'd-none' }}" data-office-punch data-office-access="{{ json_encode($access) }}" @if(! $access['office']) hidden disabled @endif>Record office punch</button>
