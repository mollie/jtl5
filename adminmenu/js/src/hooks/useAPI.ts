import axios, {AxiosInstance} from 'axios';
import usePluginInfo from './usePluginInfo';

let _api: null | API = null;

export default (): API => {
    if (_api === null) {
        const pluginInfo = usePluginInfo();
        _api = new API(pluginInfo.endpoint, pluginInfo.token);
    }
    return _api;
}

class API {

    api: AxiosInstance

    constructor(baseUrl: string, token: string) {
        //const PluginInfo = usePluginInfo();

        this.api = axios.create({
            baseURL: baseUrl,
            timeout: 60000,
            responseType: 'json',
        })
        this.api.interceptors.request.use(config => {
            config.params = config.params || {};
            config.params['token'] = token;
            config.headers = config.headers || {};
            //config.headers['Content-Type'] = 'application/json';
            return config;
        });
    }

    run = (controller: string, action: string, data: any = {}) => {
        const payload = {
            controller: controller,
            action: action,
            data: data
        };
        return this.api.request({
            method: 'POST',
            data: payload,
        });
    }

}