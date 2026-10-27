import {it,expect} from 'vitest';
import {readFileSync,readdirSync} from 'node:fs';import {join} from 'node:path';
import {parse} from '@vue/compiler-sfc';import {baseParse,type ElementNode,type RootNode,type TemplateChildNode} from '@vue/compiler-dom';
it('pairs every declared form control with contextual help, including linked labels and shared form fields',()=>{
 const controls=new Set(['InputText','InputNumber','Textarea','Select','MultiSelect','Checkbox','ToggleSwitch','Password','DatePicker','input','textarea','select']);let count=0;const missing:string[]=[];
 function files(dir:string):string[]{return readdirSync(dir,{withFileTypes:true}).flatMap(e=>e.isDirectory()?files(join(dir,e.name)):[join(dir,e.name)]);}
 for(const file of files('resources/js').filter(file=>file.endsWith('.vue'))){const template=parse(readFileSync(file,'utf8')).descriptor.template;if(!template)continue;const root=baseParse(template.content);const labels:ElementNode[]=[];
 const attribute=(node:ElementNode,name:string)=>node.props.find(p=>p.type===6 && p.name===name || p.type===7 && p.arg?.type===4 && p.arg.content===name);
 const expression=(node:ElementNode,name:string)=>{const prop=attribute(node,name);return prop?.type===6 ? JSON.stringify(prop.value?.content):prop?.type===7?prop.exp?.content:undefined;};
 function find(node:RootNode|TemplateChildNode){if(node.type===1 && node.tag==='label')labels.push(node);if('children'in node && Array.isArray(node.children))node.children.forEach(find);}find(root);
 function visit(node:RootNode|TemplateChildNode,parents:ElementNode[]=[]){
 if(node.type===1){
 if(controls.has(node.tag) && !(node.tag==='input' && ['hidden','button','submit'].some(type=>expression(node,'type')===JSON.stringify(type)))){
 count++;const id=expression(node,'input-id') ?? expression(node,'id');
 const linked=id && labels.some(label=>expression(label,'for')===id && label.loc.source.includes('FieldHelp'));
 const wrapped=parents.some(parent=>parent.tag==='ZFFormField' || parent.tag==='label' && parent.loc.source.includes('FieldHelp') || expression(parent,'class')?.includes('field-control-help') && parent.loc.source.includes('FieldHelp'));
 if(!linked && !wrapped)missing.push(file+': '+node.loc.source.slice(0,80));
 }
 parents=[...parents,node];
 }
 if('children'in node && Array.isArray(node.children))node.children.forEach(child=>visit(child,parents));
 }visit(root);
 }
 expect(missing).toEqual([]);expect(count).toBeGreaterThan(150);
});
