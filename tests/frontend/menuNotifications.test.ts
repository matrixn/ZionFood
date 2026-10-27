import { beforeEach, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useMenuNotifications, type ContentReview } from '../../resources/js/lib/menuNotifications';
import { api } from '../../resources/js/lib/http';
vi.mock('../../resources/js/lib/http',()=>({api:vi.fn(),ApiError:class extends Error {constructor(public status:number,message:string){super(message);}}}));
const mocked=vi.mocked(api);
beforeEach(()=>{setActivePinia(createPinia());mocked.mockReset();});
const result=():ContentReview=>({public_id:'draft',item_name:'Dish',item:'dish',location:'location',kind:'analyze',status:'ready',proposal:{description:'Corrected description',nutrition:{basis:'per_100g',estimated:true,values:{protein_g:5}}},source_current:true,can_write:true,report:null,error_code:null});
it('drops late inbox and detail responses after switching recipients',async()=>{
    const store=useMenuNotifications();store.identify(1);
    let finish!:(value:unknown)=>void;
    mocked.mockImplementationOnce(()=>new Promise(resolve=>{finish=resolve;}));
    const pending=store.poll();await store.poll();expect(mocked).toHaveBeenCalledTimes(1);
    store.identify(2);finish({notifications:[{public_id:'previous-user'}]});await pending;expect(store.records).toEqual([]);
    mocked.mockImplementationOnce(()=>new Promise(resolve=>{finish=resolve;}));
    const opening=store.open('location','draft');store.identify(3);finish(result());await opening;
    expect(store.review).toBeNull();expect(store.visible).toBe(false);
});
it('approves selected sections without allowing client edits to generated nutrition and rejects without invalid empty sections',async()=>{
    const store=useMenuNotifications();store.identify(1);
    mocked.mockResolvedValueOnce(result());await store.open('location','draft');
    mocked.mockResolvedValueOnce({reviewed:true}).mockResolvedValueOnce({notifications:[]});
    await store.decide('approve',['description']);
    expect(mocked).toHaveBeenNthCalledWith(2,'/app/locations/location/menu-content/drafts/draft','POST',{action:'approve',sections:['description'],changes:{description:'Corrected description'}});
    expect(store.review?.status).toBe('approved');
    mocked.mockResolvedValueOnce(result());await store.open('location','draft');
    mocked.mockResolvedValueOnce({reviewed:true}).mockResolvedValueOnce({notifications:[]});
    await store.decide('reject',[]);
    expect(mocked).toHaveBeenLastCalledWith('/app/notifications');
    expect(mocked.mock.calls[4]).toEqual(['/app/locations/location/menu-content/drafts/draft','POST',{action:'reject'}]);
    expect(store.review?.status).toBe('rejected');
});
it('keeps the proposal for correction when publication fails',async()=>{
    const store=useMenuNotifications();mocked.mockResolvedValueOnce(result());await store.open('location','draft');
    mocked.mockRejectedValueOnce(new Error('Source changed'));
    await store.decide('approve',['description']);expect(store.review?.status).toBe('ready');expect(store.error).toBe('Source changed');expect(store.busy).toBe(false);
});
