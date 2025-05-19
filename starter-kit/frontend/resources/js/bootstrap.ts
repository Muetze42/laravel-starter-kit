import Axios from 'axios'
window.axios = Axios.create()

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
