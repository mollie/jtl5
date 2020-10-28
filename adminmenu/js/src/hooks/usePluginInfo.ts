import PluginInfo from './../types/PluginInfo'

export default (): PluginInfo => {
    const elPluginInfo = document.querySelector('#pluginInfo')
    const pluginInfo = JSON.parse(elPluginInfo?.innerHTML ?? '{}') as PluginInfo;
    return pluginInfo
}