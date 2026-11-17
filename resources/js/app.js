import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import lineChart from './charts';
import exercisePicker from './exercise-picker';
import setForm from './set-form';
import workoutTimer from './timer';

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.data('setForm', setForm);
Alpine.data('exercisePicker', exercisePicker);
Alpine.data('lineChart', lineChart);
Alpine.data('workoutTimer', workoutTimer);

Alpine.start();
